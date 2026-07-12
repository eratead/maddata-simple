# MadData Marketing Site Rebuild (maddata.media)

**Status:** Planned
**Author:** Architect (2026-05-10)
**Replaces:** existing WordPress install at `maddata.media` (one-page site + Sample Page + the new Privacy/Terms pages added 2026-05-10 for Google OAuth verification)
**Builds on:** the privacy/terms drafts at [docs/legal/privacy-policy.md](../legal/privacy-policy.md) and [docs/legal/terms-of-service.md](../legal/terms-of-service.md)

---

## Goal

Replace the WordPress site at `maddata.media` with a minimal Laravel application that serves three pages (home, privacy, terms) and a contact form. No admin panel. No database (or one tiny `contact_submissions` table at most). Content is git-managed markdown that the developer edits locally and ships via `git push`.

The driving motivation is to remove the WordPress plugin attack surface, which is the #1 vector for WP compromise. Secondary motivation: stop paying WordPress's ongoing patching tax for a site that almost never changes.

## Non-goals

- No CMS / no admin panel. Content updates flow through git.
- No multi-site / no blog (yet). Add it as a follow-up if marketing wants one.
- No e-commerce, no user accounts, no authenticated pages. Marketing is fully public.
- No build pipeline more complex than the existing dashboard's Vite setup.
- Not part of the SSO rollout. Independent project; can ship before, after, or in parallel.

## High-level shape

A standalone Laravel 12 application — separate codebase from the dashboard at `maddata-simple/`. Three GET routes for public pages, one POST route for the contact form. Tailwind for styling. Markdown content rendered into Blade layouts.

```
maddata-marketing/                  (new git repo)
├── app/Http/Controllers/
│   ├── HomeController.php          GET  /
│   ├── LegalController.php         GET  /privacy, GET /terms
│   └── ContactController.php       POST /contact
├── app/Mail/
│   └── ContactFormSubmission.php   Mailable, sent via Resend
├── resources/
│   ├── content/                    ← prose lives here
│   │   ├── home.md                 (hero + section copy)
│   │   ├── privacy.md              (copied from docs/legal/)
│   │   ├── terms.md                (copied from docs/legal/)
│   │   └── clients.json            (array of {name, logo})
│   ├── views/
│   │   ├── layouts/site.blade.php  (shared <html> shell, meta, header, footer)
│   │   ├── pages/{home,privacy,terms}.blade.php
│   │   └── components/{header,footer,contact-form,placements-grid}.blade.php
│   ├── css/site.css                (Tailwind entry)
│   └── js/site.js                  (Alpine.js for contact form)
├── routes/web.php
├── public/images/{logo.svg, clients/, placements/}
└── config/services.php             (resend creds — same pattern as the dashboard)
```

## Pages

### `/` (home)

A one-page marketing site. Content roughly mirrors the existing structure plus the PDF deck's framing:

| Section | Source |
|---|---|
| Hero | "The largest mobile advertising network in Israel" + tagline |
| Audiences | Device-ID targeting, demographic, geo-based, lookalike audiences |
| Long-tail value prop | The PDF deck's "Long Tail = upper-mid-funnel" framing |
| Placements grid | Logos of premium news / sports / finance / lifestyle sites |
| Brand-lift / KPIs | The "Awareness Conversion that strengthens quality" tables |
| Clients | EPSON, Michael Kors, Nautica, Weber, Boston Proper, Uniqlo, Lupo, etc. |
| Advertising tools | Static/video interstitial, MPU static/video, Rewarded Video |
| Contact form | Name, email, company, message → emails support@erate.co.il |
| Footer | Copyright, Privacy Policy link, Terms of Service link |

### `/privacy` and `/terms`

Render the markdown files at `resources/content/privacy.md` and `terms.md` inside a minimal legal-page layout. Drop the "Drafting note" blockquote from the markdown source before shipping. **The URLs must remain `/privacy` and `/terms` to keep the Google OAuth Branding configuration valid** — see [google-sso-production-rollout.md §Legal-page publishing](google-sso-production-rollout.md#legal-page-publishing).

### `POST /contact`

Form fields (validation in parens):
- `name` — required, string, max 100
- `email` — required, valid email, max 200
- `company` — nullable, string, max 100
- `message` — required, string, min 10, max 2000
- `_honeypot` — must be empty (silent reject if filled)

On submit:
1. Throttle by IP: max 5 per hour per IP via Laravel's built-in `RateLimiter`.
2. Send a `ContactFormSubmission` Mailable to `support@erate.co.il` using the existing Resend transport (`MAIL_MAILER=resend` in the dashboard works the same way here).
3. Optionally store a row in `contact_submissions` for resilience if email delivery hiccups (open question — see below).
4. Redirect back with a flash success message.

**Spam protection:** honeypot field + IP rate limiter. Skip CAPTCHA for v1; add hCaptcha if spam volume justifies it later.

## Content workflow

```
maddata-marketing/resources/content/home.md          ← edit
maddata-marketing/resources/content/clients.json     ← edit
git commit -am "marketing: update hero copy"
git push origin main
ssh prod && cd /var/www/maddata-marketing && git pull && php artisan view:clear
```

That's the entire content-update loop. No database write, no admin login, no rebuild step beyond clearing the view cache.

For Privacy/Terms specifically, the source of truth becomes `maddata-marketing/resources/content/{privacy,terms}.md`. The drafts at `/Users/mg/projects/maddata-simple/docs/legal/*.md` get copied in once; from then on the marketing repo is canonical and the maddata-simple `docs/legal/` copy can be left as historical drafting notes (or symlinked, or deleted — open question).

## Database

Default plan: **no database**. SQLite or no migrations at all.

If we choose to persist contact submissions for resilience: one table.

```
contact_submissions
  id            BIGINT PK
  name          VARCHAR(100)
  email         VARCHAR(200)
  company       VARCHAR(100) NULL
  message       TEXT
  ip            VARCHAR(45)
  user_agent    VARCHAR(255)
  created_at    TIMESTAMP
```

No update path. Read-only for the developer via tinker if Resend ever drops a message. Decision deferred — see open question 2.

## Hosting topology

**Resolved (2026-05-10): fresh dedicated droplet** — `maddata-marketing`, single-purpose, isolated from both the dashboard's prod droplet and the staging droplet.

| Spec | Value |
|---|---|
| **Name** | `maddata-marketing` |
| **Region** | FRA1 |
| **Size** | Basic 2 GB / 50 GB / 1 vCPU ($12/mo). 1 GB is too tight for `npm run build`. |
| **OS** | Ubuntu 24.04 LTS |
| **Stack** | Nginx + PHP 8.4-FPM + Certbot |
| **SSH key** | `~/.ssh/id_rsa` |

### Why fresh-droplet over the alternatives

| Path | Verdict | Reason |
|---|---|---|
| Co-locate with dashboard prod (`164.90.233.136`) | Rejected | Marketing bug = foothold against dashboard filesystem (Resend keys, OAuth secrets, user PII). |
| Co-locate with staging (`207.154.253.28`) | Rejected | 94% disk, ~11 vhosts. Crowded box. |
| Upgrade existing WP droplet (`68.183.223.108`) in place | Rejected | Backdoors survive software cleanup. Only fresh OS is safe. Blocks rollback during swap. |
| **Fresh droplet, cutover, destroy old** | **Chosen** | Clean slate, zero-downtime DNS cutover, trivial rollback during soak. +$6.30/mo. |

### Decommission of the old WP droplet

The current `maddata.media-site` droplet (`68.183.223.108`, 1 GB / 25 GB) is decommissioned after cutover. Snapshot first (~$0.30/mo retained as backup), then destroy.

Net infrastructure delta: **+$6.30/mo** (−$6 old WP, +$12 new marketing, +$0.30 snapshot).

## DNS migration (refined for new-droplet path)

1. **24h before cutover:** lower `maddata.media` apex TTL to 60s.
2. **Provision** new droplet, bootstrap stack, deploy Laravel app.
3. **Add preview DNS:** `new.maddata.media` → new IP. Certbot. Smoke-test.
4. **Cutover:** flip apex A from `68.183.223.108` to new IP. Update `www` CNAME.
5. **Run Certbot** on new droplet for apex.
6. **Verify** all three pages, contact form, OAuth Branding URLs.
7. **Soak 7 days** — old droplet alive as rollback.
8. **Snapshot** old droplet, destroy it. Restore TTL to 3600.

The detailed cutover spec lives in the marketing repo at `/Users/mg/projects/MadData/site/docs/specs/marketing-site-rebuild.md`.

## Multi-tenant impact

None.

## Dependencies

- One new DigitalOcean droplet (`maddata-marketing`).
- Stack: Nginx, PHP 8.4-FPM, Composer, Node.js 20, Certbot.
- Resend API key (separate from dashboard).
- `league/commonmark` for markdown rendering.

## Resolved decisions (2026-05-10)

1. **Bilingual scope:** **English only.** Matches the current public face of the site; the Hebrew PDF deck stays as a separate sales artifact. File structure stays simple: `home.md`, no language suffixes.
2. **Persist contact submissions in DB:** **No.** No migrations, no `contact_submissions` table. Email through Resend is the only delivery path. If reliability becomes an issue we add the table later as a one-migration follow-up.
3. **Resend credentials:** **Separate API key for marketing.** New sender identity (e.g. `noreply@maddata.media` or `hello@maddata.media`) on a separate Resend project, isolated from the dashboard's transactional sender. Limits blast radius if either key leaks.
4. **`docs/legal/*.md` in this repo:** **Delete after the marketing repo is live and serving the canonical copies.** Git history preserves the drafting context. The conversion script `_md2html.py` and the `.html` outputs go too — they were one-shot tools.
5. **Image assets:** **Available.** Real placement logos and client logos exist; collect them during Phase 2 and drop into `public/images/{placements,clients}/`.
6. **Design vehicle:** **Claude (Artifacts) mock-up,** anchored on the dashboard's `#F97316` accent + the PDF deck's dark navy gradient + Inter font. Iterate on the mock until it looks right, then implement in Tailwind.

## Project / repo structure

**Separate Laravel project, separate git repo.** Not a folder inside `maddata-simple`.

- Local path: `/Users/mg/projects/maddata-marketing/`
- Git repo: new repository (GitHub or wherever the dashboard repo lives), e.g. `maddata-marketing`
- Production path: `/var/www/maddata-marketing/` on the existing prod droplet `164.90.233.136`
- Independent `.env`, independent Resend API key, independent Nginx vhost.
- Zero shared code with `maddata-simple`. Any pattern that feels copy-pasteable (e.g. layout helpers) stays copied — duplication beats coupling for a 3-page site.

## Phases (high level)

1. **Phase 1 — Scaffold.** New repo `maddata-marketing`, Laravel 12, Tailwind, three pages, contact form, Resend wiring. Deploy to a preview vhost at `new.maddata.media`.
2. **Phase 2 — Content + design.** Mock home page (Claude Artifacts or designer). Migrate Privacy/Terms markdown. Wire client logos and placements grid. Bilingual decisions resolved.
3. **Phase 3 — Cutover.** DNS flip, Certbot, Google OAuth Branding re-verify, smoke test.
4. **Phase 4 — Decommission.** After 7 days stable, snapshot + delete WP droplet. Cancel any WP-only DigitalOcean charges.

A detailed task list will be added to `docs/tasks/todo.md` once the open questions above are resolved.

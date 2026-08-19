# Runbook: System Health Monitor

**Spec:** [system-health-monitor.md](../specs/system-health-monitor.md)
**Built:** Phase 1 (host facts, checks, `health:check` CLI) and Phase 2 (transition-based email alerting). The admin page is Phase 3.

---

## What this is

Two root cron scripts write facts to files; Laravel reads those files and its own
markers and rolls everything up into a snapshot. Nothing in the request path
shells out, holds a sudo grant, or talks to systemd.

```
root cron  ──1 min──▶ scripts/health-facts.sh    ──▶ /run/maddata/host-facts.json      (tmpfs)
backup cron──03:00──▶ scripts/backup-production.sh ─▶ /var/backups/maddata/backup-last.json (persistent)
laravel sched─1 min─▶ health:refresh-snapshot / health:probe / QueueHeartbeatJob
                             ▼
                     php artisan health:check
```

`/run` is tmpfs, so the **host facts** vanish on reboot and are rebuilt within a
minute. That is deliberate: facts are a live sample, and a stale one surviving a
reboot would be reported as current truth.

The **backup marker** is the opposite case and lives on persistent storage at
`/var/backups/maddata/backup-last.json`, next to the backups it describes. It
records that a backup *happened*, and that has to outlive a restart — when it
briefly sat on tmpfs, a reboot made B1 report "backups are unverifiable" for the
seventeen hours until the next nightly run.

---

## Answering "is production OK?" right now

```bash
ssh root@164.90.233.136 'cd /var/www/maddata && php artisan health:check'
```

Exit codes make it composable — `0` healthy, `1` warning, `2` critical:

```bash
php artisan health:check --fail-on=crit    # default: only CRIT fails
php artisan health:check --fail-on=warn    # anything not green fails, incl. "no data"
php artisan health:check --json            # the same snapshot as machine-readable JSON
php artisan health:check --cached          # read the cached snapshot instead of rebuilding
```

Without `--cached` the command rebuilds, so it always reports current truth and
warms the cache for the next reader.

---

## Provisioning (one time, on the production droplet)

### 1. Confirm the unknowns first

Two values in `config/health.php` were guessed and must be verified, or checks
H5 and Q1 will report nonsense:

```bash
systemctl list-units --type=service | grep -iE 'queue|horizon'   # the queue worker unit name
grep -E '^(QUEUE_CONNECTION|CACHE_STORE)=' /var/www/maddata/.env  # driver reality
php-fpm8.4 -v 2>/dev/null || systemctl list-units | grep fpm      # the FPM unit name
```

Set anything that differs from the defaults in `.env`:

```bash
HEALTH_QUEUE_UNIT=maddata-queue        # default guess
HEALTH_FPM_UNIT=php8.4-fpm             # default guess
HEALTH_MARKER_STORE=redis              # PIN this — do not inherit CACHE_STORE
HEALTH_PROBE_URL=https://ad.maddata.media/up
```

`HEALTH_MARKER_STORE` matters: if `CACHE_STORE` is ever changed, every marker
written under the old store becomes invisible and every age check silently
reads STALE. Pin it explicitly.

### 2. Install the facts cron

```bash
mkdir -p /run/maddata && chmod 755 /run/maddata
chmod 700 /var/www/maddata/scripts/health-facts.sh
crontab -e
# add (root crontab):
* * * * * sleep 25; HEALTH_TLS_CERT=/etc/letsencrypt/live/new.ad.maddata.media/fullchain.pem /var/www/maddata/scripts/health-facts.sh >/dev/null 2>&1
```

Two details in that line are load-bearing:

- **`sleep 25`** offsets the run away from the top of the minute. `schedule:run`
  fires at `:00` and boots PHP; on the single-core production droplet that
  saturates the core for several seconds. Sampling CPU there measures the
  monitoring's own overhead, not the system's health — it reported a steady
  100% while the box was 98% idle. `HEALTH_CPU_SAMPLE_SECONDS` (default 15)
  controls the averaging window.
- **`HEALTH_TLS_CERT`** — the production certificate lives under
  `new.ad.maddata.media/`, not `ad.maddata.media/`, a leftover from the droplet
  migration. Without this override check P2 reads STALE forever.

Verify within two minutes:

```bash
cat /run/maddata/host-facts.json    # valid JSON, ts within 60s, units all "active"
```

The script degrades rather than failing — a missing tool yields `null` for that
field and the corresponding check reads STALE, never a crash.

### 3. Seed the backup marker

Checks B1 and B3 read a marker that `backup-production.sh` writes when it
finishes. Until the first nightly run at 03:00 there is no marker, and because
backup directories already exist on disk, **B1 will read CRIT ("marker
missing")**. That is intentional — an unverifiable backup is not a backup — but
seed it once at deploy time so the monitor starts green:

```bash
/var/www/maddata/scripts/backup-production.sh
cat /var/backups/maddata/backup-last.json

# the marker must be readable by www-data — the scheduler builds the snapshot
# as that user, and a root shell will NOT show you what it sees
sudo -u www-data php artisan health:check
```

**Always verify as `www-data`, not as root.** The scheduler, PHP-FPM and the
alerter all run as `www-data`; checks that touch the filesystem can legitimately
give a different answer to root, and root's answer is the one that does not
matter.

### 4. Record the last restore drill

The drill was performed 2026-07-12 (see [backup-restore-runbook.md](../specs/backup-restore-runbook.md)).
Without this marker B4 warns immediately:

```bash
cd /var/www/maddata
php artisan health:mark-restore-drill --note="2026-07-12 drill, restored to staging"
```

### 5. External watcher (do not skip)

Nothing running on the droplet can report that the droplet is dead. Register
`https://ad.maddata.media/up` with an external uptime monitor on a 1-minute
interval. `/up` is deliberately kept cheap and check-free precisely so it can be
hit anonymously every minute.

Monitor in use: _record the provider and dashboard URL here once configured._

---

## Alerting

`health:alert` runs every five minutes and decides, from a small state record,
whether this observation deserves an email.

**When it sends**

| Situation | Behaviour |
|---|---|
| One non-OK observation | **Silent.** A deploy restarting PHP-FPM or the queue worker resolves inside one interval. |
| Two consecutive non-OK observations | Alerts — so a real outage reaches an inbox within ~10 minutes. |
| Same problem, still failing | Repeats every `HEALTH_REALERT_HOURS` (default 6). |
| A new check starts failing | Alerts immediately, naming the new check. |
| WARN escalates to CRIT | Alerts immediately. |
| One of several problems clears | Silent — a partial recovery is not news. |
| Everything goes green | **Always** sends a recovery notice, with how long the episode lasted. A silent recovery is indistinguishable from a dead alerter. |
| Its own state is unreadable | Skips suppression and alerts anyway. A cache outage is exactly the correlated failure it exists to report. |
| The host booted moments ago | **Silent** for `HEALTH_BOOT_GRACE_SECONDS` (default 180). A reboot wipes the tmpfs facts file, so every host check briefly has no data and the system is indistinguishable from an outage. `--force` still sends. |
| The mailer throws | Logs, does not crash, does not record the notification — the next tick retries. |

**Configuration** (production `.env`):

```bash
HEALTH_ALERT_RECIPIENTS=ops@example.com,someone@example.com   # required, else nothing sends
HEALTH_REALERT_HOURS=6
```

Recipients are operator addresses, deliberately not the
`receive_activity_notifications` user flag — health mail is operations, not
product, and must reach someone even when the database is what is sick.

**Prove it works** (an untested alert path is not an alert path):

```bash
php artisan health:alert --force     # sends the current status immediately
```

Then prove it fires unprompted — stop the queue worker and wait for check Q3 to
age past 15 minutes:

```bash
systemctl stop <queue unit>
# ~20 minutes later an OUTAGE mail should arrive naming Q3
systemctl start <queue unit>
# the next run should send a recovery notice
```

**Not alerting?** In order: is `HEALTH_ALERT_RECIPIENTS` set; did `health:alert`
run (check S1, the scheduler heartbeat); has the problem been observed twice; is
the host inside its post-boot grace window; is it inside the re-alert window
(`--force` bypasses all of it).

### Rebooting a host

Nothing to do. `/run/maddata` is recreated by the facts cron within about a
minute, markers live in the database cache and survive, the backup marker is on
persistent storage, and both H1 and the alerter know to hold their judgement
while the host is still coming up. Verified on staging and production.

### What this cannot tell you

If the droplet is off, the network is gone, or SMTP is down, nothing here fires.
That is what the external watcher on `/up` is for — see step 5 above. It is the
outermost ring and nothing on the droplet can replace it.

---

## Dependency currency (Phase 4)

Checks `d1`-`d4` and `X1`/`X2` all report on the **`platform`** node, and
`config/health.php`'s `alert_excluded_nodes` routes that whole node **away from
`health:alert`**. They show on `/admin/monitor` and in `php artisan health:check`,
and they are emailed once a week by `deps:digest` (Monday 08:00 Israel time).

That is deliberate: a composer advisory is real and is not a 2am page, and
mixing the two teaches you to ignore outage mail. The consequence you should
expect: **a critical advisory turns the monitor page and the header pill red
while nothing emails.** To reverse it, set `'alert_excluded_nodes' => []`.

### Deploying Phase 4

1. **Redeploy the facts script.** `git pull` is not enough — `scripts/health-facts.sh`
   is installed separately (see §2 above) and Phase 4 changes how it counts
   security updates. Until it is reinstalled, `d3` reads STALE, which is correct
   but uninformative.
2. **Set the digest recipients** in the production `.env` (optional — it falls
   back to `HEALTH_ALERT_RECIPIENTS`):
   ```
   HEALTH_DEPS_DIGEST_RECIPIENTS=ops@example.com
   ```
3. **Do not** record a patch run just to clear `d4`. It is tempting — `d4` reads
   WARN "never recorded" until someone runs it — but marking a patch run that
   did not happen is a false record in the one check whose entire job is to
   answer "when did a human last patch this?". Leave it warning. Run
   `php artisan deps:mark-patch-run --note="what you did"` **after** you have
   actually patched, which is also when `d1`'s findings get addressed.
4. **Send one digest by hand** to prove the path works end to end. An untested
   mail path is not a mail path:
   ```
   php artisan deps:digest
   ```

### Enabling unattended-upgrades

`d3` measures whether security updates are being applied. It does not apply
them. Enable them **security pocket only**, and do not let them reboot:

```
sudo cp /etc/apt/apt.conf.d/50unattended-upgrades /etc/apt/apt.conf.d/50unattended-upgrades.bak
sudo dpkg-reconfigure --priority=low unattended-upgrades
# In 50unattended-upgrades keep ONLY the -security origin, and set:
#   Unattended-Upgrade::Automatic-Reboot "false";
```

Rebooting is a decision, not a side effect — `d3b` will tell you when one is
owed. This is provisioning: **the deploy flow never runs `apt`.**

### Why d3 does not use apt-check

`apt-check` counts packages in the *full-upgrade* change set that have a
security-pocket version, **including packages that are not installed at all**.
On this droplet that produced a permanently stuck "1 pending security update" —
`libabsl20220623t64`, not installed, and only ever arriving as a new dependency
of `libgav1-1` during a full upgrade. `apt-get install --only-upgrade` refuses
it and `unattended-upgrade` reports "no packages found", so no action could ever
clear the amber.

The facts script instead counts what `apt-get -s upgrade` would actually install
from a `-security` pocket, which is exactly what can be applied. If it cannot
compute the number it writes `null`, and `d3` reads `null` as **STALE, never
0** — a monitor that cannot count must not report "clean".

## What each failure means

| Check | CRIT means | First move |
|---|---|---|
| **H1** Host facts | The facts cron stopped. Every other H/P2/B2 reading is history, not health. | `crontab -l`, run the script by hand, check `/run/maddata` exists |
| **H2/H3/H4** CPU / memory / disk | Resource exhaustion. Disk is the usual one — backups and logs. | `du -sh /var/backups/maddata /var/www/maddata/storage/logs` |
| **H5-\*** systemd unit | That service is not running. The check is tagged to the node it belongs to, so the failing column names the layer. | `systemctl status <unit>`, `journalctl -u <unit> -n 50` |
| **P1** Public probe | Two consecutive failed HTTPS requests. Nginx, TLS, DNS or the FPM socket. | `curl -sS -o /dev/null -w '%{http_code}\n' https://ad.maddata.media/up` |
| **P2** TLS certificate | Under 7 days to expiry — certbot renewal is failing silently. | `certbot renew --dry-run`, `systemctl status certbot.timer` |
| **Q1** Queue depth | Backlog over 5,000. Worker dead or a job is looping. | `php artisan queue:monitor`, check H5 for the worker unit |
| **Q2** Failed jobs | 25+ failures in 24h. | `php artisan queue:failed` |
| **Q3** Worker heartbeat | The worker has not executed a job in 15 minutes. **systemd can say "active" for a wedged worker — this is the check that catches that.** | `systemctl restart <queue unit>`, then watch Q3 recover |
| **S1** Scheduler heartbeat | Cron is not reaching Laravel. Every scheduled job is silently not running. | `crontab -l` for the `schedule:run` line, `php artisan schedule:list` |
| **S2a/S2b** Job success | The job runs but no longer completes. | Run it by hand and read the error |
| **D1** MySQL | Unreachable. Probed on a 2s-timeout connection so it degrades instead of hanging the app. | `systemctl status mysql`, `journalctl -u mysql -n 50` |
| **D2** Redis | Unreachable, or at its memory ceiling. | `redis-cli info memory` |
| **D3** Campaign data | Never critical by design — manual uploads, so "stale" often just means nobody uploaded. | Confirm with the team before acting |
| **B1** Local backup | No backup in 50h, or backups exist with no marker (unverifiable). | Run `scripts/backup-production.sh` and read its output |
| **B2** Backup size | The newest dump is under half the median of the last seven — a partial dump that exited 0. | Inspect the newest dump, run the backup again |
| **B3** Off-site | The DO Spaces upload failed or is 50h stale. A local-only backup dies with the droplet. | Check `DO_*` credentials in `.env`, run the backup manually |
| **B4** Restore drill | No drill in 210 days. | Run [backup-restore-runbook.md](../specs/backup-restore-runbook.md), then `health:mark-restore-drill` |

**STALE is not CRIT.** It means the check has no data — usually a marker that has
not been written yet on a fresh deploy. It resolves itself once the relevant
scheduled job has run once.

---

## Adding a check

1. Extend `App\Services\Health\Checks\HealthCheck` and implement `run(): array`.
2. Wrap every probe in `guard()`. **`run()` must never throw** — a check that
   cannot answer reports CRIT tagged to its real node, and the snapshot still
   builds. That property is what makes the monitor work when the system doesn't.
3. Put thresholds in `config/health.php`, never in the class.
4. Register the class in `config('health.checks')`.
5. Add a test covering the threshold boundaries and the missing-data path.
6. Add the row to the spec's §3 catalog and to `docs/architecture_map.md`.

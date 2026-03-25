# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start all dev processes (server + queue + logs + Vite HMR)
npm run dev

# Run tests
composer run test

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Format code
./vendor/bin/pint

# Build frontend for production
npm run build

# Database migrations
php artisan migrate
php artisan migrate:rollback

# Clear caches
php artisan config:clear && php artisan cache:clear
```

## Architecture

**MadData Dashboard** — a campaign management and reporting platform for digital advertising. Clients own Campaigns; campaigns have daily performance metrics (CampaignData), placement-level metrics (PlacementData), and ad creatives (Creative → CreativeFile).

### Key Models

| Model | Purpose |
|-------|---------|
| `Campaign` | Ad campaigns with budget, expected impressions, start/end dates, status |
| `Client` | Agencies/brands; many-to-many with User |
| `CampaignData` | Daily performance metrics (impressions, clicks, cost) |
| `PlacementData` | Placement-level metrics |
| `Creative` / `CreativeFile` | Ad creatives and their uploaded files |
| `Role` | Permission groups with JSON `permissions` array |
| `ActivityLog` | Audit trail for changes |

### Permissions

Dual-layer permission system:
- **New (preferred)**: `Role` model with a JSON `permissions` array; users have `role_id`
- **Legacy fallback**: `is_admin`, `is_report`, `can_view_budget` booleans on `User`
- `User::hasPermission($key)` checks the role first, then falls back to legacy fields
- Middleware: `EnsureUserIsAdmin`, `EnsureUserIsCampaignManager`

### Routes

- All web routes require `auth` middleware
- Admin routes: `/admin/*` prefix with `admin` middleware
- API reporting routes: `/api/reports/*` using `auth:sanctum` + `CheckTokenExpiry` middleware
- Token management for campaign managers at `/tokens`

### Frontend

- **Blade + Alpine.js** — reactive UI with `x-data`, `x-show`, `x-bind`, etc.
- **Tailwind CSS** with a custom design system (colors, shadows, animations in `tailwind.config.js`)
- **Vite** for asset bundling with Laravel plugin; assets in `resources/js/` and `resources/css/`
- **DataTables** used for sortable/searchable tables (see `components/scripts/datatables.blade.php`)
- Reusable Blade components in `resources/views/components/` (e.g., `page-box`, `dialog`, `autocomplete-input`, `sidebar`)

### API

Sanctum token-based API under `/api/reports` exposes:
- Summary metrics
- Metrics by date
- Metrics by placement

Tokens are created via `TokenController` and have custom expiry logic checked by `CheckTokenExpiry` middleware.

### Testing

PestPHP v3 with Laravel plugin. Tests use SQLite in-memory (configured in `phpunit.xml`). Feature tests in `tests/Feature/`, unit tests in `tests/Unit/`.
### Strict Coding Standards & Workflow Rules (AI Context)
To ensure smooth AI-assisted development and avoid repetitive errors, the following rules MUST be strictly followed:

* **Auto-Testing:** ALWAYS run tests (or the specific test filter) immediately after creating or modifying migrations, models, or test files to catch syntax or DB errors before presenting the code as "done".
* **Test Coverage for New Features:** Every new feature, bug fix, or behavioral change MUST include corresponding Pest tests. After implementing code, ALWAYS write or update tests in `tests/Feature/` before considering the task complete. Use the `tester` agent if needed. If validation rules change, test both valid and invalid input. If a controller method changes, test the endpoint. No feature is "done" without tests.
* **Pivot Tables:** Follow Laravel's strict alphabetical, singular naming convention for pivot tables (e.g., `campaign_client`, never `clients_campaigns`).
* **Blade & Alpine.js Security:** When outputting dynamic data into Alpine.js (`x-data`, `x-bind`) or Blade HTML attributes, ALWAYS escape JSON properly using `e(json_encode($data))` or `@js($data)` to prevent JS parse errors and console warnings.
* **Proactive Refactoring:** When asked to simplify or refactor (e.g., via `/simplify`), you must proactively address related improvements like adding missing DB indexes, extracting Blade components, and cleaning up traits/permissions, unless explicitly told not to.
* **Living Architecture Map:** Whenever you create a new file (Action, Model, Command, Controller, etc.) or make a significant architectural modification to an existing file, you MUST update `docs/architecture_map.md`. Insert the file into the correct architectural layer table, providing its exact relative path and a 1-4 sentence explanation of its Single Responsibility within the architecture.

## UI/UX & Design System — Design Remaster

We are remastering the MadData UI to a **"Modern Enterprise SaaS"** look, using `docs/demo_maddata_enterprise.html` as the reference implementation. All new and refactored views must follow this spec.

### Visual Direction
- **Sidebar**: Dark `bg-[#111827]`, active nav item has orange left border + subtle gradient highlight (see `.nav-active` in demo)
- **Accent color**: `#F97316` (orange) for active states, highlights, CTR values, progress bars, primary buttons
- **Background**: `bg-gray-50` page, `bg-white` cards/tables with `border border-gray-200 rounded-lg`
- **Typography**: Inter font; label text `text-[10px] uppercase tracking-wider font-semibold`; values `font-black`; muted text `text-gray-400`
- **Stat cards**: Tinted colored boxes (`bg-blue-50/50 border border-blue-100`) with oversized ghost icon (`absolute -right-3 -bottom-3 opacity-10 w-14 h-14`) and `hover:-translate-y-0.5` lift

### Tools & Libraries

| Tool | Usage | Rule |
|------|-------|------|
| **Flowbite CSS** | Component styles, design tokens | Use via CDN or npm. CSS only. |
| **Flowbite JS** | ❌ NEVER include | Conflicts with Alpine.js on DOMContentLoaded |
| **Flowbite Icons** | Inline SVG icons throughout the UI | Installed: `npm install flowbite-icons`. Source paths: `node_modules/flowbite-icons/src/outline/[category]/[name].svg` and `solid/`. Copy path data directly into inline `<svg>`. |
| **Alpine.js** | ALL interactivity — dropdowns, modals, tabs, datepicker, accordion, toggles | No jQuery, no Flowbite JS |
| **Tailwind CSS** | All layout and utility styling | No custom CSS unless unavoidable |

### Flowbite Icons — How to Use
Read the SVG file from `node_modules/flowbite-icons/src/outline/` to get the exact path, then inline it:
```html
<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
  <!-- paste path(s) from the .svg file -->
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..."/>
</svg>
```
Categories: `general/`, `user/`, `arrows/`, `media/`, `e-commerce/`, `files-folders/`, `text/`, `education/`

### Alpine.js Datepicker Pattern
The project uses a pure Alpine.js calendar (no Flowbite datepicker, no flatpickr). The `dateRange()` function lives inside `reportApp()` in the report views. See `docs/demo_maddata_enterprise.html` for the full reference implementation with range highlighting, month navigation, and from/to sequential picking.

### What to Change (Remaster Scope)
- **Sidebar** (`resources/views/components/sidebar.blade.php`): dark bg, orange active state
- **Page layouts**: gray-50 background, white content panels with border/rounded
- **Stat/metric cards**: tinted boxes with ghost Flowbite icon, hover lift
- **Tables**: clean `divide-y` style, orange accent on key metrics (CTR etc.)
- **Buttons & forms**: consistent orange primary, gray secondary
- **Icons**: replace Heroicons/custom SVGs with Flowbite icons throughout

### What Stays the Same
- Blade component structure (`<x-page-box>`, `<x-dialog>`, etc.) — refactor internals, keep API
- Alpine.js for all reactivity
- Tailwind CSS utility-first approach
- All backend logic, permissions, routes — design-only remaster

## Development Workflow
- **Environment**: This is a local development environment (Laravel Herd / Mac). Do NOT attempt to connect to production via SFTP or SSH.
- **Database**: Local MySQL for development. Migrations (`php artisan migrate`) should be run locally.
- **Deployment**: Code is deployed via Git. We push to a `staging` branch for review before pushing to `main` (production).

## Staging Server
- **Host**: 207.154.253.28
- **User**: root
- **SSH key**: `~/.ssh/id_rsa` (has passphrase — run `ssh-add ~/.ssh/id_rsa` if not loaded)
- **Project path**: `/var/www/dev/maddata-simple`
- **DB**: `maddata_simple`, user `webusr` (password stored in memory, NOT here)

### Staging deploy sequence
```bash
# 1. Push code
git push origin main:staging

# 2. SSH and update
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "cd /var/www/dev/maddata-simple && git fetch && git checkout staging && git pull && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear"

# 3. Dump local DB and import to staging
mysqldump -u root maddata_simple > /tmp/staging_dump.sql
scp -i ~/.ssh/id_rsa /tmp/staging_dump.sql root@207.154.253.28:/tmp/
ssh -i ~/.ssh/id_rsa root@207.154.253.28 "mysql -u webusr -p'PASS' maddata_simple < /tmp/staging_dump.sql"
```


## Agent Team

This project uses a multi-agent system. Each agent has a defined role:

| Agent | Trigger | Responsibility |
|-------|---------|----------------|
| **architect** | "plan", "design", "architect" | Designs features, writes specs to `docs/specs/` |
| **builder** | "build", "implement", "code" | Implements specs, writes actual Laravel code |
| **reviewer** | "review", "check", "quality" | Reviews code quality and Laravel conventions |
| **tester** | "write tests", "test coverage" | Writes PHPUnit/Pest tests |
| **security** | "security check", "audit" | Audits for vulnerabilities |
| **documenter** | "document", "API docs" | Writes docs, PHPDoc, README |
| **performance** | "performance", "optimize", "slow", "N+1", "cache" | Audits for bottlenecks, N+1 queries, caching gaps |
| **frontend** | "blade", "view", "UI", "component", "tailwind", "page", "form", "alpine" | Builds Blade templates, Tailwind styling, Alpine.js interactivity |
| **server** | "deploy", "server", "nginx", "php version", "ssl", "staging", "ssh" | Server management, deployments, PHP/Nginx/MySQL config, package updates |

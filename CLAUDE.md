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


## UI/UX & Design System
- We are transitioning to a "Modern Enterprise SaaS" design.
- **Styling**: Use strictly Tailwind CSS. Avoid custom CSS unless absolutely necessary.
- **Components**: Always use the defined Blade components in `resources/views/components/` instead of writing raw HTML (e.g., `<x-page-box>`, `<x-dialog>`).
- **Colors**: Use the custom semantic colors defined in `tailwind.config.js` (e.g., `text-textMuted`, `bg-surface`, `bg-background`).
- **Interactivity**: Use Alpine.js (`x-data`, `x-show`, etc.) for dropdowns, modals, and toggles. Do NOT use jQuery.

## Development Workflow
- **Environment**: This is a local development environment (Laravel Herd / Mac). Do NOT attempt to connect to production via SFTP or SSH.
- **Database**: Local MySQL for development. Migrations (`php artisan migrate`) should be run locally.
- **Deployment**: Code is deployed via Git. We push to a `staging` branch for review before pushing to `main` (production).
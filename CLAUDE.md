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

## UI/UX Design System
Design specs in `docs/design-system.md`. Key tokens: accent `#F97316`, sidebar `#111827`, Inter font. Reference: `docs/demo_maddata_enterprise.html`.

## Development Workflow
- **Environment**: This is a local development environment (Laravel Herd / Mac). Do NOT attempt to connect to production via SFTP or SSH.
- **Database**: Local MySQL for development. Migrations (`php artisan migrate`) should be run locally.
- **Deployment**: Code is deployed via Git. We push to a `staging` branch for review before pushing to `main` (production).

## Staging Server
Details in memory and `docs/specs/production-deploy-plan.md`. Use the `server` agent for deployments.

## Agent Team
Agent definitions live in `.claude/agents/`. Use sub-agents sparingly -- each costs ~15K-30K tokens of context loading.

## AI Agent Workflow & Behavioral Rules
To ensure high-quality output and maintain context across long development sessions, you MUST adhere to the following behavioral standards:

* **Plan First (Task Management):** For any non-trivial task (3+ steps), you must first write a detailed, checkable plan to a `docs/todo.md` file. Do not start writing application code until the user approves the plan. Mark items complete as you go.
* **The Self-Improvement Loop:** We maintain a `docs/lessons.md` file. After ANY correction from the user regarding architecture, syntax, or business logic, you MUST update this file with a new rule to prevent the same mistake. You must review `docs/lessons.md` at the start of new tasks.
* **Verification Before Done:** Never mark a task complete without proving it works. You must run the relevant tests, check logs, and demonstrate correctness. Ask yourself: "Would a Staff Engineer approve this?" before presenting it.
* **Autonomous Bug Fixing (No Laziness):** When given a bug report, error log, or failing CI test: just fix it. Find the root cause and resolve it. Do not ask for hand-holding or permission to write the fix. 
* **Demand Elegance (Simplicity First):** Make every change as simple as possible. Impact minimal code. For complex changes, pause and ask yourself if there is a more elegant, Laravel-native solution before over-engineering. No temporary fixes; adhere to senior developer standards.

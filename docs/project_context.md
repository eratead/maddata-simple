# Project Context: MadData

**CRITICAL INSTRUCTION FOR ALL AGENTS:** Before designing, building, testing, or reviewing any feature, you MUST read and understand this document. It defines the business logic, architectural constraints, and technical stack of the MadData platform.

## 1. Business Model & Product Vision
MadData is an **"AI-Driven Managed Service"** for digital advertising. 
* **NOT a Self-Serve DSP:** Clients DO NOT manage their own bids, targeting, or budgets directly. 
* **The "Black Box" Arbitrage:** The client submits a brief and an overall budget. MadData's internal admins (and the AI engine) route this budget behind the scenes to various ad networks (DSPs, Social, Native) to maximize ROI. We sell *results*, not platform access.
* **Client View vs. Admin View:** Clients see a simplified, read-only dashboard with aggregated results (Impressions, Clicks, CTR) grouped by "Placements". Admins see the complex "Cockpit" where budgets are split across different networks.

## 2. Multi-Tenant Architecture & RBAC
The system uses a strict hierarchical multi-tenant model and a dynamic Role-Based Access Control (RBAC) system.
* **Agency:** An advertising agency (e.g., McCann). Contains multiple clients.
* **Client:** The actual advertiser (e.g., McDonald's). Belongs to ONE Agency (`agency_id`).
* **User:** Independent entity. Users are granted access via Pivot Tables:
  * `agency_user`: Grants access to ALL clients within the assigned agencies.
  * `client_user`: Grants access ONLY to specific assigned clients.
* **Roles:** Users have a `Role` (e.g., Admin, Viewer). Roles dictate *what* they can do (via boolean columns like `is_admin`, `can_manage_users`, `can_view_budget`). Pivot tables dictate *where* they can do it.
* **Security Rule:** Always prevent Privilege Escalation. A user cannot grant a role that has higher permissions than their own.

## 3. Core AdTech Entities
* **Campaign (Insertion Order):** The client-facing order. Defines the global budget, dates, and creative assets.
* **LineItem (The Router):** The internal admin-facing entity. A Campaign is split into multiple LineItems. Each LineItem represents a specific execution on a specific network (e.g., $10k to Facebook, $20k to StackAdapt).
* **Creative:** Ad assets. Because external networks (Facebook, Taboola) require manual approval, Creatives and LineItems must track `dsp_status` (pending_audit, approved, rejected) via backend Polling Commands.

## 4. Integrations & The Adapter Pattern
We integrate with various external media buying platforms using a strict **Adapter Pattern**.
* **Interface:** `MediaProvider` or `DspAdapter`.
* **Implementations:** `StackAdaptAdapter` (DSP), `FacebookAdapter` (Social Walled Garden), `TaboolaAdapter` (Native).
* **Rule:** The core Laravel application should never know the specific implementation details of a DSP. It calls generic methods like `pushCampaign()` or `pullDailyReports()` on the interface.

## 5. Technology Stack & Coding Standards
* **Backend:** Laravel 12 (PHP 8.2+). Strict types, thin controllers, heavy use of Service classes and Form Requests.
* **Testing:** PestPHP v3 (DO NOT use standard PHPUnit class syntax).
* **Database:** MySQL. Always use migrations with `up()` and `down()`. Protect against N+1 queries.
* **Frontend / UI System:** * **Engine:** Blade templates with Alpine.js (`x-data`) for reactivity.
  * **Styling:** Tailwind CSS. DO NOT write custom CSS.
  * **Components & Icons:** Flowbite UI component structures and Flowbite SVG icons.
  * **Design Language:** Modern Enterprise UI. Backgrounds use `bg-gray-50`. Cards are white with subtle shadows. The primary brand color is Orange (`#F97316`). Focus on clean, scannable data tables and minimalist forms.

## 6. Agent-Specific Directives
* **@architect:** Always respect the Multi-Tenant pivot tables. Never design features that break the Client/Admin view separation.
* **@builder:** When writing Blade files, strictly adhere to the Tailwind/Flowbite design language. Ensure all database queries eager-load relationships to prevent N+1.
* **@tester:** Write tests using PestPHP v3 syntax (`it('does something', function() { ... })`). Ensure RBAC policies are heavily tested for unauthorized access.

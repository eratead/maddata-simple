# UI/UX & Design System -- Design Remaster

We are remastering the MadData UI to a **"Modern Enterprise SaaS"** look, using `docs/demo_maddata_enterprise.html` as the reference implementation. All new and refactored views must follow this spec.

## Visual Direction
- **Sidebar**: Dark `bg-[#111827]`, active nav item has orange left border + subtle gradient highlight (see `.nav-active` in demo)
- **Accent color**: `#F97316` (orange) for active states, highlights, CTR values, progress bars, primary buttons
- **Background**: `bg-gray-50` page, `bg-white` cards/tables with `border border-gray-200 rounded-lg`
- **Typography**: Inter font; label text `text-[10px] uppercase tracking-wider font-semibold`; values `font-black`; muted text `text-gray-400`
- **Stat cards**: Tinted colored boxes (`bg-blue-50/50 border border-blue-100`) with oversized ghost icon (`absolute -right-3 -bottom-3 opacity-10 w-14 h-14`) and `hover:-translate-y-0.5` lift

## Tools & Libraries

| Tool | Usage | Rule |
|------|-------|------|
| **Flowbite CSS** | Component styles, design tokens | Use via CDN or npm. CSS only. |
| **Flowbite JS** | NEVER include | Conflicts with Alpine.js on DOMContentLoaded |
| **Flowbite Icons** | Inline SVG icons throughout the UI | Installed: `npm install flowbite-icons`. Source paths: `node_modules/flowbite-icons/src/outline/[category]/[name].svg` and `solid/`. Copy path data directly into inline `<svg>`. |
| **Alpine.js** | ALL interactivity -- dropdowns, modals, tabs, datepicker, accordion, toggles | No jQuery, no Flowbite JS |
| **Tailwind CSS** | All layout and utility styling | No custom CSS unless unavoidable |

## Flowbite Icons -- How to Use
Read the SVG file from `node_modules/flowbite-icons/src/outline/` to get the exact path, then inline it:
```html
<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24">
  <!-- paste path(s) from the .svg file -->
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..."/>
</svg>
```
Categories: `general/`, `user/`, `arrows/`, `media/`, `e-commerce/`, `files-folders/`, `text/`, `education/`

## Alpine.js Datepicker Pattern
The project uses a pure Alpine.js calendar (no Flowbite datepicker, no flatpickr). The `dateRange()` function lives inside `reportApp()` in the report views. See `docs/demo_maddata_enterprise.html` for the full reference implementation with range highlighting, month navigation, and from/to sequential picking.

## What to Change (Remaster Scope)
- **Sidebar** (`resources/views/components/sidebar.blade.php`): dark bg, orange active state
- **Page layouts**: gray-50 background, white content panels with border/rounded
- **Stat/metric cards**: tinted boxes with ghost Flowbite icon, hover lift
- **Tables**: clean `divide-y` style, orange accent on key metrics (CTR etc.)
- **Buttons & forms**: consistent orange primary, gray secondary
- **Icons**: replace Heroicons/custom SVGs with Flowbite icons throughout

## What Stays the Same
- Blade component structure (`<x-page-box>`, `<x-dialog>`, etc.) -- refactor internals, keep API
- Alpine.js for all reactivity
- Tailwind CSS utility-first approach
- All backend logic, permissions, routes -- design-only remaster

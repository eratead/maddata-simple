# Spec: Remove "Visible" Column From By-Date Report Table

## Goal
Remove the **Visible** column from the "By Date" report table on the campaign dashboard. The column currently shows `visible_impressions` per day and is considered redundant alongside the Viewability % KPI card above the table.

**Scope is UI-only.** We are NOT dropping the `visible_impressions` database column, the Viewability KPI card, the By-Placement table's Visible column, or anything in the API / exports. Only the by-date table column and the data it pulls through from the service layer for that specific table.

## What Stays (Out of Scope)

| Item | Reason |
|---|---|
| `campaign_data.visible_impressions` DB column | Still written by import pipeline; still feeds KPI + placement table |
| `$summary['visible']` in `CampaignMetricsService::getDashboardData()` | Used by the Viewability % KPI card (blade line 274) |
| `$dashPlacementRows` `visible` key | Feeds the By-Placement table (different report) |
| `ReportApiController::byPlacement` `visible_impressions` | Public API contract — can't break |
| `CampaignByDatesSheet` export | Does not contain a visible column today |
| Test fixtures using `visible_impressions` | Still valid — the underlying DB column remains |

## Files To Change

### 1. `resources/views/dashboard/index.blade.php`
Three removals inside the `{{-- BY DATE TABLE --}}` block (around lines 440–490):

- **Header cell** (line 448) — the `<th>` with `Visible` label and `sortDateCol==='visible'` binding.
- **Row cell** (line 463) — the `<td>` with `x-text="nf(row.visible)"`.
- **Totals cell** (line 477) — the `<td>` with `{{ number_format($summary['visible'] ?? 0) }}`.

No other blade sites reference the by-date visible column. The Viewability % card at line 274 and the By-Placement table must stay untouched.

### 2. `app/Services/CampaignMetricsService.php`
In `getDashboardData()`, the `$dashDateRows` map (currently lines 78–87) must stop emitting the `visible` key:

- **Remove** line 82: `'visible' => (int) $r->visible_impressions,`
- **Keep** `$dashPlacementRows` mapping (lines 89–98) — by-placement table still needs it.
- **Keep** `$summary['visible']` population (line 49) — KPI card still needs it.

## Alpine State Audit

`sortDateCol` can currently be set to `'visible'` by clicking that header. Once the header is removed, no user interaction can set that value, so no defensive cleanup of `_sortRows` is required. The sort function iterates on whatever key is set — an unreachable `'visible'` branch (if any exists) can be left alone or removed opportunistically; it is not load-bearing.

**Builder should verify:** open `resources/views/dashboard/index.blade.php` around the Alpine `_sortRows` helper and delete any `case 'visible':` branch **only** if it's clearly dead. Do not refactor the sort function.

## Multi-Tenant / RBAC Impact
None. This is a pure UI simplification inside an already-authorized dashboard view. No route, policy, or pivot change.

## API / Contract Impact
None. `/api/reports/*` responses are untouched. Only the Blade view and its server-side data-preparation mapping change.

## Tests

### Existing tests to check
- `tests/Feature/DashboardControllerTest.php` — uses `visible_impressions` as a factory field. This is a DB-column write, **not** an assertion against the rendered `visible` key. Should continue to pass without edits.
- `tests/Feature/ReportApi*Test.php` — only assert on by-placement visible. Unaffected.

### New assertion (optional but recommended)
Add a single assertion to `DashboardControllerTest` (or the nearest equivalent feature test that renders the campaign dashboard):

```
it('does not render a Visible column in the by-date table', function () {
    // ... existing setup that hits the dashboard route ...
    $response->assertOk();
    $response->assertDontSeeText('Visible', escape: false);
    // Or, more precisely, assert the column header markup is gone.
});
```

Scope the assertion narrowly — the word "Visible" may appear elsewhere on the page in unrelated copy. Prefer asserting that `row.visible` is not present in the compiled markup, or that the by-date `<thead>` contains exactly the expected columns.

## Dependencies
None. No package, migration, or service change.

## Confirmed Decisions
1. **Viewability % KPI card stays** (blade line 274, `$summary['visible']`). Only the column in the by-date datatable is removed.
2. **Responsive layout is acceptable** with the column gone — no extra layout work required.

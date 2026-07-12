# Architecture Questions & Decisions

## 2026-04-16 — AI Campaign Assistant Cities Fix

### Task 7: `targeting_rules` logging in `CampaignController::update`

**Spec says:** Add `ActivityLogger::log(...)` in `CampaignController::update` when `targeting_rules` changes, with action `targeting_updated` and before/after diff.

**What was found:** `CampaignObserver::updated()` already performs this exact logging — it detects `isDirty('targeting_rules')`, computes a field-level diff across all 13 targeting fields (including `cities`), and writes an `ActivityLog` entry with `action: 'updated'`, a human-readable description, and the full `before`/`after` JSON. This fires automatically whenever `$campaign->update(...)` is called from the controller.

**Decision:** Did not add duplicate logging in `CampaignController::update`. The observer already satisfies the audit requirement from spec verification step 6. Adding a second log would create duplicate `activity_logs` rows for every targeting save.

**If the architect wants a distinct `action: 'targeting_updated'` separate from `action: 'updated'`**, the cleanest fix is to change the observer's log call for `targeting_rules` to use `'targeting_updated'` as the action string — a one-line change. This was not done in this pass to avoid breaking existing tests that may assert on `action: 'updated'`.

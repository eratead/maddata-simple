# Staging QA Checklist

**Date:** 2026-03-24
**URL:** Your staging URL
**Test with:** Admin account (Michael) + one non-admin account

---

## 1. Login & 2FA

### 1.1 First login — 2FA setup
- [ ] Login as admin → **Should see:** 2FA setup page (QR code + TOTP app instructions)
- [ ] Scan QR with Google Authenticator / Authy → enter code → **Should see:** Dashboard
- [ ] Logout → login again → **Should see:** 2FA challenge page (enter code, no QR)
- [ ] Enter correct code → **Should see:** Dashboard

### 1.2 Disabled user login
- [ ] Create a test user via admin, then disable them → try login with their credentials
- [ ] **Should see:** Error message "Your account has been disabled"

### 1.3 Registration blocked
- [ ] Go to `/register` → **Should see:** 404 (registration disabled)

---

## 2. Agencies

### 2.1 Agency list
- [ ] Go to Admin > Agencies → **Should see:** 10 agencies (ARLO, OCEAN, McCann, TABRY, AFAK, GAL-OREN, Azrieli College, GO, SCALA, Lapam)
- [ ] Each shows client count

### 2.2 Agency edit
- [ ] Click edit on ARLO → **Should see:** Agency name field, client count as orange link
- [ ] Click the client count link → **Should see:** Clients page filtered to ARLO's clients only
- [ ] Browser back → **Should return** to agency edit

### 2.3 Agency create with manager
- [ ] Go to Agencies > Create → **Should see:** Agency name + collapsible "Initial Agency Manager" section
- [ ] Create agency "Test Agency" with manager (name, email, password) → **Should see:** Success message
- [ ] Check Users list → **Should see:** New manager user with "Agency Manager" role
- [ ] **Cleanup:** Delete Test Agency after test (only if 0 clients)

---

## 3. Users (Admin)

### 3.1 User list
- [ ] Go to Admin > Users → **Should see:** All 16 users with columns: Name, Email, Role, Agency, Clients, Actions
- [ ] Filter by agency "ARLO" → **Should see:** Only users assigned to ARLO
- [ ] Filter by role "Admin" → **Should see:** Only Michael and Eran
- [ ] Clear filters → all users shown
- [ ] Browser back after filtering → **Should see:** Filters preserved in URL

### 3.2 User edit — agency assignment
- [ ] Edit user "ARLO" (yaniv@arlodigital.co.il) → **Should see:** Agency Assignments section
- [ ] Verify ARLO is listed as an assigned agency
- [ ] Try adding a second agency → click "Add Agency" → select one → Save
- [ ] **Should see:** User now has 2 agencies listed
- [ ] Remove the second agency → Save → back to 1 agency

### 3.3 User edit — multi-agency with specific clients
- [ ] Edit a non-admin user → Add 2 agencies
- [ ] Set Agency A to "All agency clients", Agency B to "Specific clients" → check 1-2 clients
- [ ] Save → re-open edit → **Should see:** Settings preserved correctly

### 3.4 User roles
- [ ] Verify Michael (ID 1) has Admin role
- [ ] Verify Eran (ID 2) has Admin role
- [ ] Verify all other users have "Viewer Campaign + Budget" role
- [ ] Try changing a user's role → Save → **Should persist**

### 3.5 Anti-escalation (admin)
- [ ] Edit a user → try assigning a role with `is_admin` permission while logged as admin → **Should work**

---

## 4. Agency Manager Experience

### 4.1 Setup an agency manager
- [ ] As admin, edit user "ARLO" (yaniv) → set role to "Agency Manager" → assign to ARLO agency → Save
- [ ] Login as ARLO user (or use test user with Agency Manager role)

### 4.2 Sidebar
- [ ] **Should see:** "AGENCY: ARLO" section in sidebar with "Users" and "Clients" links
- [ ] Should NOT see admin "Manage" section (Agencies, Roles, etc.)

### 4.3 Agency user management
- [ ] Click Users → **Should see:** Users in ARLO agency only
- [ ] Click "+ New User" → **Should see:** Create form with role dropdown
- [ ] Role dropdown should NOT show "Agency Manager" or "Admin" roles
- [ ] Role dropdown should NOT show roles with permissions the manager doesn't hold (e.g., "Third Party Communicator" if manager lacks upload/logs perms)
- [ ] Create a test user with "Viewer Campaign + Budget" role → **Should succeed**
- [ ] Set client access to "Specific clients" → check 1 client → Save
- [ ] Verify new user appears in list with "Specific" badge
- [ ] Edit the user → change to "All agency clients" → Save → **Should show** "All" badge
- [ ] Disable the user → **Should see:** Greyed out row with "Disabled" badge
- [ ] Re-enable via edit (is_active toggle) → **Should see:** Active again

### 4.4 Agency client management
- [ ] Click Clients → **Should see:** Only ARLO's clients
- [ ] Create a new client "Test Client" → **Should succeed**, agency auto-set to ARLO
- [ ] Edit the client → change name → Save → **Should persist**
- [ ] Delete the client (only works if no campaigns) → **Should succeed**
- [ ] Try deleting a client with campaigns → **Should see:** Error "Cannot delete client with existing campaigns"

### 4.5 Anti-escalation (agency manager)
- [ ] Try creating a user and selecting a role you shouldn't be able to → **Should not appear in dropdown**
- [ ] Manager should NOT be able to disable themselves → **Should see:** "You" label instead of Disable button

---

## 5. Campaigns & Dashboard

### 5.1 Campaign list
- [ ] Go to Campaigns → **Should see:** Paginated list (25 per page)
- [ ] Pacing bars: orange when < 100%, **green when >= 100%**
- [ ] Non-admin user should only see their clients' campaigns

### 5.2 Campaign dashboard
- [ ] Click a campaign → **Should see:** Dashboard with metrics
- [ ] Impression pacing bar shown (NOT budget pacing) — visible to ALL users
- [ ] Pacing bar green when >= 100%, orange when below
- [ ] Date filter works: select date range → metrics update

### 5.3 Campaign edit
- [ ] Edit a campaign → **Should see:** No "Video Campaign" toggle (removed)
- [ ] If campaign is_video = true → **Should see:** Purple "Video Campaign" read-only badge
- [ ] Required Sizes accordion → select sizes → Save → refresh → **Should see:** Sizes preserved
- [ ] Clear all sizes → Save → refresh → **Should see:** No sizes selected

### 5.4 Report upload
- [ ] Upload an Excel report to a campaign → **Should see:** Success with impression/click counts
- [ ] If Excel has video columns → campaign should auto-set as video

---

## 6. Clients (Admin)

### 6.1 Client list with agency filter
- [ ] Go to Admin > Clients → **Should see:** Agency filter dropdown next to search
- [ ] Select "OCEAN" → **Should see:** Only OCEAN's clients, with count shown
- [ ] Click "Clear" → back to all clients
- [ ] Agency column shows agency name for each client

### 6.2 Client CRUD
- [ ] Create client → assign to an agency → Save → **Should appear** in list with agency
- [ ] Edit client → change agency → Save → **Should update**
- [ ] Delete client (no campaigns) → **Should succeed**

---

## 7. Roles

### 7.1 Role list
- [ ] Go to Admin > Roles → **Should see:** 4 roles (Admin, Agency Manager, Viewer Campaign + Budget, Third Party Communicator)

### 7.2 Role permissions
- [ ] Edit "Agency Manager" role → **Should see:** Permissions checkboxes
- [ ] Verify `can_manage_users` and `can_manage_clients` are checked
- [ ] Try granting a permission you don't hold → **Should see:** 403 error (escalation prevention)

---

## 8. API (Postman)

### 8.1 Token authentication
- [ ] Create a new token via UI (Admin > API Settings) → copy token
- [ ] In Postman: `GET /api/reports/summary/{campaign_id}` with Bearer token → **Should see:** 200 with metrics JSON
- [ ] Without token → **Should see:** 401
- [ ] With expired token → **Should see:** 401

### 8.2 API endpoints
- [ ] `GET /api/reports/summary/{id}` → **Should return:** campaign_id, impressions, clicks, ctr, uniques
- [ ] `GET /api/reports/by-date/{id}?start=2025-01-01&end=2025-12-31` → **Should return:** by_date array
- [ ] `GET /api/reports/by-placement/{id}` → **Should return:** by_placement array
- [ ] `GET /api/reports/campaigns` → **Should return:** Paginated list

### 8.3 Cross-tenant security
- [ ] Try accessing a campaign that doesn't belong to the token user's clients → **Should see:** 403

---

## 9. Audiences

### 9.1 Audience upload
- [ ] Go to Admin > Audiences → Upload the CSV file (IL Segments - 24.03.csv)
- [ ] **Should see:** "Imported 184 audiences" (all rows, including 5+ depth segments)
- [ ] Verify audiences with deep paths display correctly (e.g., main: "Interests", sub: "Content > Lifestyle", name: "Food fans")

---

## 10. Activity Logs

### 10.1 Verify logging
- [ ] Go to Admin > Activity Logs
- [ ] After performing CRUD operations above → **Should see:** Log entries for:
  - Agency created/updated
  - User created/updated/disabled
  - Client created/updated/deleted
  - Role created/updated
- [ ] Each entry shows: user who performed action, timestamp, description

---

## 11. Edge Cases

### 11.1 Data integrity
- [ ] Verify all 10 agencies migrated correctly from production (names match)
- [ ] Verify all 34 clients have an agency assigned
- [ ] Verify all campaigns still show correct data in dashboard (spot-check 2-3)

### 11.2 Concurrent access
- [ ] Open same campaign in 2 tabs → edit in one → save → refresh other → **Should see:** Updated data

### 11.3 Mobile responsive
- [ ] Open staging on mobile (or browser dev tools) → sidebar should collapse, tables should scroll horizontally

---

## Summary

| Section | Tests |
|---------|-------|
| Login & 2FA | 4 |
| Agencies | 4 |
| Users (Admin) | 7 |
| Agency Manager | 9 |
| Campaigns & Dashboard | 6 |
| Clients (Admin) | 4 |
| Roles | 2 |
| API | 5 |
| Audiences | 1 |
| Activity Logs | 1 |
| Edge Cases | 3 |
| **Total** | **46** |

---

## After QA Passes

If all tests pass, the staging is approved for production deploy.
Follow: `docs/specs/production-deploy-plan.md`

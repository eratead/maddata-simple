# Idea: AI Assistant & Tool Layer Architecture

**Status:** Idea — discuss with Eran after production deploy
**Date:** 2026-03-23
**Priority:** Future feature — foundational for platform evolution

---

## The Vision

Build an AI assistant that can interact with MadData (and later Erate) through natural language. Starting as an in-app chatbot, evolving into an external agent that users can talk to from Claude.ai or other interfaces.

### Example interactions:
- "How are ARLO's campaigns performing this week?"
- "Create a new user for client McDonald's with viewer access"
- "Check campaign X — if it performs well, prolong it another week"
- "Which campaigns are underperforming? Show me anything below 50% pacing"

---

## Why This Matters

1. **User value** — agency managers who aren't technical get instant insights without navigating dashboards
2. **Premium feel** — AI-powered AdTech platform commands higher pricing
3. **Cross-project reuse** — same architecture works for Erate and future products
4. **External agents** — users can interact with MadData from Claude.ai, Slack, WhatsApp without opening the app

---

## Architecture: Three Layers

```
Layer 1: Granular Service Actions (reusable across projects)
    ├── CampaignService::getPerformance(campaign, user)
    ├── CampaignService::extend(campaign, days, user)
    ├── CampaignService::listUnderperforming(threshold, user)
    ├── UserService::create(name, email, agency, role, user)
    ├── ClientService::list(agency, user)
    └── ... every business operation as a callable method

Layer 2: Tool Registry (maps AI tools to service methods)
    ├── get_campaign_performance → CampaignService::getPerformance()
    ├── extend_campaign → CampaignService::extend()
    ├── create_user → UserService::create()
    └── ... filtered by user permissions per request

Layer 3: AI Interface (multiple entry points, same tools)
    ├── Chat widget in MadData UI
    ├── Chat widget in Erate UI
    ├── MCP server for external Claude agents
    └── Future: Slack bot, WhatsApp, API
```

### Cross-Project Reuse

```
MadData                          Erate
┌─────────────┐                 ┌─────────────┐
│ Chat UI     │                 │ Chat UI     │
│ Controllers │                 │ Controllers │
└──────┬──────┘                 └──────┬──────┘
       │                               │
┌──────▼──────────────────────────────▼──────┐
│           AI Tool Layer (shared)            │
│  ┌─────────────────────────────────────┐   │
│  │ Tool Registry                       │   │
│  │ - MadData tools (campaign, client)  │   │
│  │ - Erate tools (project-specific)    │   │
│  │ - Shared tools (user, reporting)    │   │
│  └─────────────────────────────────────┘   │
└──────┬──────────────────────────────┬──────┘
       │                              │
┌──────▼──────┐                ┌─────▼───────┐
│ MadData     │                │ Erate       │
│ Services    │                │ Services    │
└─────────────┘                └─────────────┘
```

---

## External Agent Flow (MCP / API)

For the scenario: "Check this campaign in MadData. If it performs well prolong it to another week"

```
User talking to Claude (claude.ai or API)
    ↓
Claude recognizes it needs MadData data
    ↓
Calls MadData MCP server / API endpoint
    ↓
Tool Registry receives: check_campaign_performance(id=42)
    ↓
CampaignService::getPerformance(42) → returns metrics
    ↓
Claude analyzes: "pacing is 87%, CTR above average"
    ↓
Claude decides to prolong → calls: extend_campaign(id=42, days=7)
    ↓
Tool Registry: action requires user confirmation
    ↓
Returns to Claude: "Pending approval"
    ↓
Claude asks user: "Campaign X is performing well. Extend by a week?"
    ↓
User: "Yes"
    ↓
Execute: CampaignService::extend(42, 7)
```

---

## Tool Registry Design

Each tool is a registered entry with metadata:

```php
'get_campaign_performance' => [
    'service' => CampaignService::class,
    'method' => 'getPerformance',
    'permissions' => ['can_view_campaigns'],
    'parameters' => [
        'campaign_id' => ['type' => 'integer', 'required' => true],
    ],
    'description' => 'Get performance metrics for a campaign including impressions, clicks, CTR, pacing',
    'read_only' => true,  // no confirmation needed
],

'extend_campaign' => [
    'service' => CampaignService::class,
    'method' => 'extend',
    'permissions' => ['can_edit_campaigns'],
    'parameters' => [
        'campaign_id' => ['type' => 'integer', 'required' => true],
        'days' => ['type' => 'integer', 'required' => true],
    ],
    'description' => 'Extend a campaign end date by N days',
    'read_only' => false,  // requires user confirmation
],
```

The AI receives this as Claude tool definitions. Permissions filter which tools are available per user.

---

## Implementation Phases

### Phase 0: Extract Services (prerequisite)
Refactor fat controllers into granular services. Each service method must be:
- Self-contained (no HTTP request/response dependency)
- Permission-aware (accepts a User parameter, checks access)
- Returns structured data (arrays/DTOs, not views or redirects)

Services to extract:
1. `CampaignService` — performance metrics, pacing, extend, list
2. `ReportApiMetricsService` — deduplicate summary/byDate/byPlacement calculations
3. `CreativeFileService` — upload, dimension detection, re-encoding
4. `AudienceService` — import, list, CRUD
5. `UserManagementService` — create, update, disable (shared between admin + agency)

### Phase 1: Read-Only Chat (1-2 days)
- In-app chat widget (Alpine.js floating bubble)
- Read-only tools: campaign performance, client list, underperforming campaigns
- No state changes, just data retrieval
- Admin-only initially

### Phase 2: Write Actions with Confirmation (2-3 days)
- Action cards in chat UI: "Create user X?" with Confirm/Cancel buttons
- Tools: create user, assign client, extend campaign
- Permission-scoped tool list per user
- Activity logging for all AI-initiated actions

### Phase 3: Agency Manager Access (1 day)
- Enable chat for agency managers
- Tool list filtered by agency scope + role permissions
- Agency managers can only query/modify within their agency

### Phase 4: MCP Server (2-3 days)
- Expose Tool Registry as an MCP server
- External Claude agents can call MadData tools
- Authentication via Sanctum tokens
- Same permission + confirmation flow

### Phase 5: Proactive Alerts (future)
- Scheduled job checks campaign health
- Pushes alerts: "Campaign X dropped below 50% pacing"
- Could notify via in-app, email, or external agent

---

## Dependencies

- **Claude API** with tool_use — already using Anthropic API in CampaignAssistantController
- **MCP SDK** for Phase 4 — Anthropic provides this
- **No new packages needed** for Phases 1-3

## Existing Code to Build On

- `CampaignAssistantController` — already calls Claude API, handles chat history, returns structured JSON
- `CampaignMetricsService` — already extracts campaign metrics (dashboard use)
- `ReportImportService` — pattern for service extraction
- Permission system + `accessibleClientIds()` — ready for tool scoping

---

## Open Questions for Discussion with Eran

1. **Priority vs other features** — is this more valuable than other roadmap items?
2. **Which users first** — admins only, or agency managers too?
3. **MCP timeline** — do we want external agent access soon, or is in-app enough for now?
4. **Erate integration** — when do we start planning the shared tool layer?
5. **Cost** — Anthropic API costs per chat message. Budget considerations for heavy usage?

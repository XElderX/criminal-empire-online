# Criminal Empire Online v0.6.5.1 API


## v0.6.5.1 Map Shop UX & Navigation Hotfix

Shops are intended to be opened from the World Map/location maps. Hotspots with shops expose clickable shop markers and shop callouts. The standalone Shops page remains available for map deep links such as `/?shop=market-tool-shop`, but normal navigation no longer treats it as a global market tab.

Tutorial update handling now avoids reopening the World Systems tutorial for players who already completed the v0.6.4 guide/update path.

## v0.6.5 Map Shops & Item Availability Expansion

Inventory is now owned-item management. Buying and selling equipment happens through map-linked shops and dealers. Most transactions require the boss to be physically present at the shop hotspot.

### Shop endpoints

```text
GET  /api/shops
GET  /api/shops/{slug}
GET  /api/shops/{slug}/items
POST /api/shops/{slug}/buy
POST /api/shops/{slug}/sell
GET  /api/shops/{slug}/transactions
GET  /api/world-map/locations/{slug}/shops
```

Buy/sell requests are backend validated. The backend ignores frontend prices and checks the shop catalog, `ShopConfig`, local presence, player cash, stock, level/reputation requirements, disabled item flags, legal/black-market availability, and inventory ownership.

### Shop configuration

`backend/app/Config/ShopConfig.php` controls global item availability, allowed shop types, buy/sell status, stock, restock timing, level/reputation gates, black-market/future-only states, and source hints for missing item requirements.

### Inventory change

`GET /api/items` is now a legacy source-hint endpoint. Global buying through `POST /api/items/{id}/buy` is blocked for normal players. Use map shops instead.


## v0.6.4 World Tutorial & Player Guidance Update

The tutorial system now supports tutorial versioning, a full new-player world guide, and a shorter existing-player `World Systems Update` guide.

### Tutorial endpoints

- `GET /api/tutorial` — current tutorial state, backward-compatible with previous UI.
- `GET /api/tutorial/current` — current versioned tutorial state.
- `GET /api/tutorial/steps` — current tutorial modules and steps.
- `POST /api/tutorial/objective` — records a specific tutorial objective event such as viewing a page. This does not directly complete a step.
- `POST /api/tutorial/advance` — attempts to advance the current step after backend validation.
- `POST /api/tutorial/skip` — skips the active tutorial without granting completion rewards.
- `POST /api/tutorial/reopen` — reopens tutorial/help mode without resetting rewards.
- `POST /api/tutorial/reset-dev` — admin-only tutorial reset for development/testing.
- `GET /api/tutorial/guide` — persistent guide/help content.
- `GET /api/help/tips?page={page}` — contextual help tips for a page.
- `POST /api/help/tips/{tipKey}/dismiss` — dismiss a contextual help tip.
- `POST /api/help/tips/{tipKey}/reopen` — reopen a dismissed contextual help tip.

### Tutorial validation

Tutorial progress is validated by backend objective types such as `view_page`, `travel_to_location`, `explore_hotspot`, `complete_job`, `complete_quick_crime`, `inspect_candidate`, `hire_crew`, `equip_item`, `inspect_dirty_job`, `execute_dirty_job`, `view_heat_page`, `view_territory`, `view_warehouse`, and `view_guide`.

Rewards are small, once-only, and do not reset money, crew, inventory, warehouse, heat, map state, tutorial history, or progression.


All protected endpoints require:

```text
Authorization: Bearer <token>
Content-Type: application/json
```

Errors are returned as JSON with a `message` field. Game state, timers, ownership, rewards, and outcomes are validated by the backend.

## Authentication and player

```text
POST /api/register
POST /api/login
GET  /api/me
```

`GET /api/me` includes `version` and `release_title`.

## Tutorial

```text
GET  /api/tutorial
POST /api/tutorial/advance
POST /api/tutorial/skip
POST /api/tutorial/reopen
```

Example acknowledgement request:

```json
{
  "step_code": "welcome"
}
```

The service validates gameplay-backed steps from database state. The frontend cannot arbitrarily complete them.

## Starter jobs

```text
GET  /api/jobs
GET  /api/jobs/active
POST /api/jobs/{id}/start
POST /api/job-runs/{id}/complete
```

## Dirty Jobs

```text
GET  /api/dirty-jobs
GET  /api/dirty-jobs/active
GET  /api/dirty-jobs/history
GET  /api/dirty-jobs/{id}
POST /api/dirty-jobs/{id}/accept
POST /api/dirty-job-runs/{id}/prepare
POST /api/dirty-job-runs/{id}/assign-crew
POST /api/dirty-job-runs/{id}/execute
POST /api/dirty-job-runs/{id}/decision
POST /api/dirty-job-runs/{id}/resolve
```

Preparation example:

```json
{
  "action_code": "scout_target"
}
```

Crew assignment example:

```json
{
  "role_code": "lookout",
  "member_id": 4
}
```

Decision example:

```json
{
  "choice_code": "leave_quietly"
}
```

The exact preparation actions, required roles, and event choices are returned with the opportunity or active run.

## Recruitment and crew

```text
GET  /api/recruitment
POST /api/recruitment/{id}/hire
GET  /api/my-gang
GET  /api/my-gang/{id}
POST /api/my-gang/{id}/equip
POST /api/my-gang/{id}/equipment/{equipmentId}/unequip
POST /api/my-gang/{id}/dismiss
GET  /api/my-gang/{id}/history
POST /api/my-gang/{id}/pay-overdue
```

Equip request example:

```json
{
  "source_type": "item",
  "source_id": 18,
  "slot": "tool"
}
```

Dismissal request example:

```json
{
  "reason": "Repeatedly ignored instructions"
}
```

## Item and weapon inventory

```text
GET  /api/items
POST /api/items/{id}/buy
GET  /api/inventory
GET  /api/weapons
POST /api/weapons/{id}/buy
```

## Warehouses

```text
GET  /api/warehouses
GET  /api/warehouse-listings
POST /api/warehouse-listings/{id}/purchase
POST /api/warehouses/{id}/transfer
POST /api/warehouses/{id}/vehicles/{vehicleId}/store
POST /api/warehouses/{id}/vehicles/{vehicleId}/remove
POST /api/warehouses/{id}/upgrades/{upgradeId}/purchase
```

Transfer example:

```json
{
  "asset_type": "item",
  "asset_id": 18,
  "quantity": 2,
  "direction": "deposit"
}
```

Supported asset types are returned by the inventory and warehouse payloads. Deposits and withdrawals are transactional and capacity checked.

## Heat

```text
POST /api/heat/lay-low
```

## Existing game systems

```text
GET  /api/crimes
POST /api/crimes/{id}/commit
GET  /api/crime-logs
GET  /api/drug-market
GET  /api/gangs
POST /api/gangs
GET  /api/territories
```

## Administrator

Administrator authorization is checked in the controller.

```text
GET  /api/admin/dashboard
GET  /api/admin/audit
GET  /api/admin/economy
POST /api/admin/users/{id}/energy/refill
POST /api/admin/users/{id}/cash/set
```


## Crew portrait response metadata

`GET /api/my-gang`, `GET /api/my-gang/{id}`, and `GET /api/recruitment` include backend-resolved portrait metadata.

```json
{
  "portrait": {
    "identity_key": "portrait-set-017",
    "gender": "female",
    "stage": "adult",
    "stage_label": "Adult",
    "age_range": "32–40",
    "resolved_asset_stage": "adult",
    "url": "/assets/crew/portraits/portrait-set-017/adult.webp",
    "thumbnail_url": "/assets/crew/portraits/portrait-set-017/thumbs/adult.webp",
    "fallback_url": "/assets/crew/portraits/fallback.svg",
    "focal_x": 50,
    "focal_y": 42,
    "uses_fallback": false,
    "uses_stage_fallback": false
  },
  "life_stage": {
    "key": "adult",
    "label": "Adult",
    "minimum_age": 32,
    "maximum_age": 40,
    "age_range": "32–40",
    "recruitable": true
  }
}
```

Portrait stage is derived from the NPC's age. No portrait identity or stage mutation endpoint is exposed to clients. Existing records without a portrait are assigned lazily and can be backfilled in bulk with `php commands/crew-portraits.php backfill`.

## v0.4 Crimes Expansion API

Authenticated player routes:

- `GET /api/crimes` returns legacy quick crimes plus v0.4 discovery locations, active opportunities, active crime runs, recent outcomes, known NPC contacts, available crew, available equipment, and preparation options.
- `POST /api/crime-locations/{code}/explore` spends the configured location cost and creates a player-specific rumor, lead, confirmed opportunity, or suspicious/trap lead.
- `GET /api/crime-opportunities/{id}` returns one owned opportunity with preparation, crew, and equipment state.
- `POST /api/crime-opportunities/{id}/investigate` improves a rumor/lead toward a confirmed or suspicious opportunity.
- `POST /api/crime-opportunities/{id}/prepare` applies a backend-defined preparation option by code.
- `POST /api/crime-opportunities/{id}/assign-crew` stores owned active crew assignments for the opportunity.
- `POST /api/crime-opportunities/{id}/assign-equipment` stores owned item/weapon selections for the opportunity.
- `POST /api/crime-opportunities/{id}/start` starts the run, locks the user/opportunity, calculates risk from crew/equipment/preparation/heat/contact quality, and either resolves immediately or returns a pending event.
- `POST /api/crime-runs/{id}/decision` validates a backend-owned event choice and resolves the run.
- `POST /api/crime-opportunities/{id}/abandon` closes an owned opportunity without reward.
- `GET /api/npc-contacts` lists persistent NPCs the player has met through the crime loop.

Authenticated admin routes:

- `GET /api/admin/npcs` lists NPCs with filters/search/sort using the existing admin role check.
- `GET /api/admin/npcs/{id}` returns an NPC detail payload with portrait, life stage, stats, flags, relationships, timeline events, crime involvement, and status logs.

All crime outcomes remain backend-authoritative. The frontend can submit only selected opportunity IDs, owned crew/equipment IDs, preparation codes, and backend-defined decision codes; it cannot submit a result or random outcome.

## v0.4.1 Quick Crimes API

Authenticated player endpoints:

- `GET /api/quick-crimes` — list quick crime templates, requirement status, active quick events, history, and progression summary.
- `GET /api/quick-crimes/{id}` — inspect one quick crime template.
- `POST /api/quick-crimes/{id}/prepare` — apply one lightweight preparation option by `code`.
- `POST /api/quick-crimes/{id}/start` — start a quick crime using a backend-validated idempotency key, optional crew IDs, optional equipment, district, and target key.
- `GET /api/quick-crimes/runs/{runId}` — view a quick crime run owned by the player.
- `POST /api/quick-crimes/runs/{runId}/decision` — submit a backend-validated event decision.
- `POST /api/quick-crimes/runs/{runId}/resolve` — resolve a run that has no pending event.
- `GET /api/quick-crimes/history` — recent quick crime history.
- `GET /api/player/progression` — player XP and recent progression logs.

Rewards, XP, loot, success rolls, cooldowns, and skill progression are all calculated server-side.

## v0.5 Heat & Police API

Authenticated player endpoints:

- `GET /api/heat` — boss, gang, crew, district, investigation, warning, log, and reduction overview.
- `GET /api/heat/logs` — recent heat logs.
- `GET /api/heat/reduction-options` — available reduction actions and lock reasons.
- `POST /api/heat/reduce` — execute a backend-validated reduction action by `code`.
- `POST /api/heat/lie-low` — compatibility endpoint for stronger short lie-low.
- `POST /api/heat/process-day` — idempotent daily heat decay and investigation processing.
- `GET /api/investigations` and `GET /api/investigations/{id}` — active investigation views.
- `POST /api/investigations/{id}/respond` — respond with legal/silence/cooperation actions.
- `GET /api/boss`, `GET /api/boss/history`, `GET /api/boss/succession` — boss character, timeline, and succession candidate.
- `GET /api/update-notices/pending` and `POST /api/update-notices/acknowledge` — one-time update notice workflow.

Admin endpoints:

- `GET /api/admin/heat`
- `GET /api/admin/investigations`
- `GET /api/admin/characters/{type}/{id}/heat`


## v0.5.1 Boss Character Integration

- `GET /api/boss` now includes a `skills` object with boss operational stats.
- `GET /api/my-gang` includes the boss as a crew-like dossier entry with `id: 0`, `member_type: boss`, and `is_boss: true`.
- `GET /api/my-gang/0` returns the boss profile in the same shape as a crew dossier.
- `GET /api/my-gang/0/history` returns boss history in the same shape used by crew history timelines.
- Crime assignment endpoints accept `gang_member_id: 0` to select the boss actor.
- Quick crime start accepts `crew_ids: [0]` to select the boss actor.


## v0.6 World Map API

Authenticated player endpoints:

- `GET /api/world-map` — world map response with regions, current location, summary, map assets, and legend.
- `GET /api/world-map/regions` — active world regions.
- `GET /api/world-map/regions/{slug}` — region map with hotspots, territory summary, activity summary, and risk data.
- `GET /api/world-map/regions/{slug}/locations` — hotspots for a region.
- `GET /api/world-map/locations/{slug}` — one hotspot with linked actions, territory, risk summary, and travel preview.
- `GET /api/world-map/locations/{slug}/activities` — activity links, local presence, travel purpose, nearby action counts, and previews for one hotspot.
- `GET /api/world-map/current-location` — player current region/hotspot with recent travel state.
- `POST /api/world-map/travel` — backend-validated travel by `region_slug` or `location_slug`, with route costs, events, history, unlocked local actions, and updated stats.
- `POST /api/world-map/travel-and-explore` — travels first, then explores the hotspot only if travel succeeds.
- `GET /api/world-map/travel-history` — recent travel log entries for the authenticated player.
- `GET /api/world-map/territories` — map-linked territory summaries.

Read-only admin endpoints:

- `GET /api/admin/world-map`
- `GET /api/admin/world-map/regions`
- `GET /api/admin/world-map/locations`

Travel deducts configured cash/energy costs and updates `user_location_state`. Existing users default to Main City / Slums when no location state exists.

## v0.6.1 Map Gameplay Integration API

Authenticated endpoints added/extended:

- `GET /api/world-map/locations/{slug}/activities` — returns real local activity groups, quick-crime previews, dirty-job previews, territory summary, heat summary, and contextual route hints.
- `GET /api/world-map/regions/{slug}/activities` — aggregates local activity groups across the region.
- `POST /api/world-map/locations/{slug}/explore` — costs 3 energy, respects hotspot cooldown, and can reveal a local opportunity.
- `GET /api/quick-crimes?region=&location=` — filters quick crimes by local map rules while preserving the generic no-filter list.
- `GET /api/dirty-jobs?region=&location=` — filters/prioritizes dirty jobs by local map rules while preserving the generic no-filter list.

Quick-crime start may include `region_slug` and `location_slug`. The backend validates whether the action is allowed at that hotspot and whether current-location travel is required.


## v0.6.3.1 Street Job NPC Assignment Hotfix

- `GET /api/jobs` now includes `min_assigned_members`, `assignable_crew_count`, `requires_npc_assignment`, and `assignment_hint` for Street Job cards.
- `POST /api/jobs/{id}/start` now requires at least one assigned active NPC crew member for every Street Job, even older jobs with `min_gang_size = 0`.
- Boss/fake crew ids such as `0` are rejected for Street Jobs.


## v0.6.3 Meaningful Travel & Local Presence

Travel responses include `travelResult`, `fromLocation`, `toLocation`, `routeType`, `costs`, `event`, `warnings`, `unlockedActions`, `localActivitySummary`, `heatChange`, `historyEntry`, `currentLocation`, and `updatedPlayerStats`.

Local presence rules are enforced server-side. The frontend may show remote previews, but the backend decides whether a Quick Crime, Dirty Job, or hotspot exploration can actually start from the player’s current location.

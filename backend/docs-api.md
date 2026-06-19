# Criminal Empire Online v0.3.6 API

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

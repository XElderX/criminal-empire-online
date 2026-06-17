# Criminal Empire Online v0.3 API

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

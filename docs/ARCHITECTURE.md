# Architecture

## Application shape

Criminal Empire Online v0.3 uses a lightweight custom PHP REST API and a React/TypeScript frontend.

```text
criminal-empire-online/
├── backend/
│   ├── app/
│   │   ├── Config/       balance and application configuration
│   │   ├── Controllers/  HTTP request handling
│   │   ├── Core/         router, database, request, response, bootstrap
│   │   ├── Middleware/   bearer authentication
│   │   ├── Models/       small table-oriented model helpers
│   │   └── Services/     business and game-domain logic
│   ├── commands/         world/developer CLI entry points
│   ├── database/
│   │   ├── migrations/   ordered raw SQL migrations
│   │   └── seeders/      ordered development seed data
│   ├── public/           API front controller
│   ├── routes/api.php    route registration
│   └── tests/            unit, contract, and MySQL integration tests
└── frontend/
    └── src/
        ├── api/          typed fetch client
        ├── components/   shared UI components
        ├── pages/        feature pages
        ├── App.tsx       authentication and page routing
        └── styles.css    application styling
```

## Backend conventions

- Controllers parse HTTP input, call services, and return JSON responses.
- Business rules live in small named services, not controllers.
- Database access uses PDO prepared statements.
- Money is stored as integer whole-dollar values in the current economy model. No floating-point database money columns are introduced.
- Mutating multi-table operations use transactions and row locks where ownership or duplication matters.
- The backend remains authoritative for timers, rewards, success rolls, heat, injuries, arrests, equipment condition, storage capacity, and tutorial validation.
- User-owned records are always queried with `user_id` ownership constraints.
- Repeated reward, purchase, execution, and transfer requests are handled idempotently or rejected by state checks and unique constraints.

## Authentication

Registration and login issue random bearer tokens. Only the SHA-256 token hash is stored in `api_tokens`. Passwords use Argon2id where available through PHP’s password API.

Authenticated requests send:

```text
Authorization: Bearer <plain-token-returned-at-login>
```

## Tutorial domain

`TutorialService` reads the ordered step definitions from `GameConfig`, stores persistent state in `user_tutorial_progress`, and records validated step events in `tutorial_step_logs`.

Most steps are completed only when corresponding game records exist. Acknowledgement-only educational steps can be advanced by the player. Rewards use a stored claimed-reward list so repeated requests cannot pay twice.

Existing users are inserted as tutorial-completed during migration. New registrations create active tutorial progress.

## Dirty Job domain

The Dirty Job system separates reusable content from player-specific state:

- `dirty_job_templates`: category, story, requirements, timings, reward ranges, event choices, and structured preparation definitions.
- `dirty_job_opportunities`: a finite, expiring opportunity generated for one player and linked to an NPC contact and district.
- `dirty_job_runs`: the accepted operation and backend-controlled lifecycle.
- `dirty_job_preparations`: purchased or performed preparation actions.
- `dirty_job_assignments`: one crew member per operation role.
- `dirty_job_equipment`: equipment snapshots reserved for the operation.

`DirtyJobGeneratorService` maintains a bounded player opportunity pool. `DirtyJobService` owns acceptance, preparation, assignment, execution, decisions, resolution, rewards, crew release, and consequences. `DirtyJobCalculator` contains the centralized success and outcome calculation.

## Crew and equipment

`player_gang_members` remains the persistent crew record. Dismissal changes state rather than deleting the row. `crew_history` stores recruitment, jobs, injuries, arrests, dismissal, and return events.

Structured equipment uses:

- `item_definitions` and `user_items` for general tools, clothing, utility gear, and loot.
- Existing `weapons` and `user_weapons` for weapons.
- `crew_equipment` as the single loadout link for either owned item type.

Effect JSON is aggregated by `EquipmentEffectService`; item-name comparisons are not scattered through controllers.

## Warehouse and storage

The building architecture is extensible:

- `building_types`
- `property_listings`
- `player_buildings`
- `building_upgrades`
- `player_building_upgrades`

Warehouse contents use `warehouse_storage`, which can refer to a general item, weapon, drug, or abstract asset category. Full vehicles use `vehicles.warehouse_id` and warehouse vehicle slots. `storage_logs` records transfers and vehicle movements.

`WarehouseService` validates ownership, quantities, reserved stock, category capacity, vehicle slots, and transaction safety.

## World processing

Normal page requests do not run the full world simulation. CLI commands process:

- Hourly energy and crew recovery
- Daily heat decay and Dirty Job opportunity refresh
- Weekly salaries and warehouse operating costs

The command files are designed for manual development use or cron scheduling.

## Frontend conventions

The v0.3 frontend is split into feature pages instead of one large component. API state remains server-authoritative. Components show backend validation errors, disabled states, empty states, confirmation prompts, and operation timers without exposing hidden random rolls.

## Security and future hardening

Already included:

- Prepared statements
- Hashed passwords and tokens
- Ownership checks
- Transactional inventory transfers
- Server-authoritative calculations
- Audit and economy logs
- Restricted local-only tutorial reset command

Recommended before a public production launch:

- API rate limiting
- Token expiry and refresh flow
- Production HTTPS and secure reverse-proxy configuration
- Structured centralized logging
- Backup and migration deployment policy
- Broader MySQL concurrency tests
- More granular administrator permissions

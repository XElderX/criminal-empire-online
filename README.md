
## v0.6 — Game Map & Territories

Criminal Empire Online now uses **Grimwater County** as its fictional world name. The v0.6 update adds an interactive world map with major regions, local sub-maps, clickable hotspots, travel/current-location state, territory links, map risk summaries, and local map assets.

Major regions:

- Main City
- Suburbs
- Industrial Zone
- Docks
- Rural County
- Forest / Hills
- Shore / Beach / Sea
- Old Town
- Highway / Outskirts

New map gameplay connects existing systems instead of replacing them: Crimes, Quick Crimes, Dirty Jobs, Recruitment, Businesses, Drug Market, Warehouse, Heat & Police, and Territories.

# Criminal Empire Online v0.6.2.1 — Recruitment Lifecycle Split

Criminal Empire Online is a browser-based, primarily single-player criminal empire simulation. The player begins with $500, performs small NPC-provided jobs, recruits a crew, equips members, plans structured Dirty Jobs, manages heat and consequences, and now discovers more complex crime opportunities through NPCs and city locations.

## Technology

- PHP 8.2+ custom REST API
- PDO with MySQL 8 or MariaDB
- React, TypeScript, and Vite
- Bearer-token authentication
- Raw SQL migrations and seeders

This project is **not Laravel**. Business logic is organized into readable controllers and services under `backend/app`.




## v0.5.1.3 highlights

- Every Dirty Job now requires at least `1` assigned crew member, even if an older template was configured with `0`.
- Dirty Job execution is blocked both in the backend and UI until the minimum crew assignment is present.
- The earlier admin heat reset and boss-name setup changes remain in place.

## v0.5 highlights

- Expanded heat into personal boss heat, crew heat, NPC heat, gang heat, district heat, and investigation pressure.
- Added heat logs, police investigations, police events, boss history, and update notices.
- Added a Heat & Police page with boss status, gang forecast, crew heat, active investigations, district heat, recent logs, and reduction actions.
- Added daily idle decay (-5 heat) and weekly quiet bonus (-15 heat) through idempotent daily processing.
- Improved heat reduction with stronger lie-low, bribe contact, lawyer/legal help, destroy evidence, and send high-heat crew away.
- Dismissing high-heat crew can lower boss/gang heat, but angry dismissed NPCs may become revenge risks with sabotage or retaliation events.
- Added boss character status, health, injury/arrest/death-ready fields, boss history, and automatic succession service.
- Added one-time update notice modal after login that must be confirmed once per account.

## v0.4.2 highlights

- Added 10-minute cooldowns for each fallback street action and kept the crimes area split into focused subtabs.
- Quick crimes are small cooldown-based actions for early money, XP, loot, and fallback play when no major lead is ready.
- Added requirement validation for player level, energy, heat, item tags, optional crew, equipment ownership, and cooldown availability.
- Added lightweight preparation options that can adjust success, heat, event risk, XP, and loot without guaranteeing success.
- Added backend-owned random quick-crime events and decision choices for street complications.
- Added XP rewards, player level progression logs, crew XP support, rare skill progression logs, economy logs, repeat action logs, and cooldown enforcement.
- Added quick-crime UI cards, missing item notices, cooldown display, result panels, event decision cards, and recent quick action history.

## v0.4 highlights

- New multi-step Crimes loop: explore, discover, investigate, prepare, assign crew/equipment, execute, resolve event choices, and review outcomes.
- Crime opportunities are discovered through locations and NPC information instead of all appearing as static buttons.
- Persistent NPC contacts, informants, witnesses, rivals, and historical dead NPC records now exist in the crime world.
- Crime preparation actions affect success, police pressure, witness risk, disaster chance, and loot quality through structured effects.
- Crew stats, roles, loyalty/morale, and selected equipment influence crime risk.
- Random crime events can pause for player decisions such as patrols, witnesses, rival interference, equipment problems, buyer hesitation, or extra loot.
- Outcomes can resolve as critical success, success, partial success, failed escape, injury, arrest, police trap, or abandoned opportunity.
- NPC relationships and timeline memory update after crime interactions.
- New Admin NPC browser shows persistent NPC world state with filters/search/sorting, stats, affiliations, relationships, timelines, and dead NPC watermark treatment.
- v0.3.6.5 asset primer applied refreshed supplied crime/job/territory art plus new v0.4 item cards for Screwdriver Set, First-Aid Kit, Surveillance Kit, Dark Clothing, Work Uniform, Vehicle Tools, and Duffel Bag.


## v0.3.6 asset placement

Vite serves files from `frontend/public`, so game assets should be placed under `frontend/public/assets/...` and referenced in React as `/assets/...`.

Recommended folders:

- `frontend/public/assets/crew/male/`
- `frontend/public/assets/crew/female/`
- `frontend/public/assets/icons/items/weapons/`
- `frontend/public/assets/icons/items/protection/`
- `frontend/public/assets/icons/items/tools/`
- `frontend/public/assets/icons/items/vehicles/`
- `frontend/public/assets/icons/items/valuables/`
- `frontend/public/assets/icons/items/contraband/`
- `frontend/public/assets/icons/roles/`
- `frontend/public/assets/crimes/`
- `frontend/public/assets/jobs/`
- `frontend/public/assets/businesses/`
- `frontend/public/assets/territories/`
- `frontend/public/assets/placeholders/`

Crew portrait naming:

```text
/assets/crew/male/crew_male_001_16_24.webp
/assets/crew/male/crew_male_001_25_31.webp
/assets/crew/male/crew_male_001_32_40.webp
/assets/crew/male/crew_male_001_41_55.webp
/assets/crew/male/crew_male_001_56_70.webp
/assets/crew/female/crew_female_001_16_24.webp
/assets/crew/female/crew_female_001_25_31.webp
/assets/crew/female/crew_female_001_32_40.webp
/assets/crew/female/crew_female_001_41_55.webp
/assets/crew/female/crew_female_001_56_70.webp
```

Continue the number up to `050` where assets are available. Male NPCs must only use `/assets/crew/male/...`; female NPCs must only use `/assets/crew/female/...`.

Fallback behavior:

1. The helper tries the requested gender-safe age-stage WebP.
2. If that stage is missing, the UI tries the same identity's adult `32_40` WebP.
3. If that is missing, the UI tries `/assets/crew/male/default.webp` or `/assets/crew/female/default.webp`.
4. If that is missing, the UI tries `/assets/crew/default.webp`.

Item, role, crime, dirty job, business, and territory image lookup is centralized in `frontend/src/data/assetManifest.ts`. Database names are normalized into slugs so small naming differences do not break the UI.


### Applied production-style asset pack

This ZIP includes local cropped WebP game assets applied to the real frontend mappings:

- item icons under `frontend/public/assets/icons/items/...`
- role icons under `frontend/public/assets/icons/roles/...`
- crime thumbnails under `frontend/public/assets/crimes/...`
- dirty job thumbnails under `frontend/public/assets/jobs/...`
- business thumbnails under `frontend/public/assets/businesses/...`
- territory backgrounds under `frontend/public/assets/territories/...`
- WebP fallback images under `frontend/public/assets/placeholders/...`

The React asset maps point to WebP assets first. For safety, matching `.svg` filenames are also present, but they now embed the same WebP artwork instead of showing the old empty placeholder graphics. This means stale dev-server code that still requests `/assets/.../*.svg` will also display real visuals.

## Native quick start

```bash
cp backend/.env.example backend/.env
nano backend/.env

cd backend
php database/migrate.php
php -S 127.0.0.1:8085 -t public public/index.php
```

In a second terminal:

```bash
cd frontend
npm install
npm run dev -- --host 127.0.0.1
```

Open `http://127.0.0.1:5173`.

For the complete Ubuntu and MySQL setup, see [`docs/INSTALL_NATIVE_MYSQL.md`](docs/INSTALL_NATIVE_MYSQL.md).

## One-click launcher

To start both servers and install the recommended cron jobs for world processing with one script, run:

```bash
cd /var/www/criminal-empire-online
./start-dev.sh
```

This script:

- opens separate terminals for the backend and frontend
- installs or refreshes a named cron block for the current user
- preserves any other existing crontab entries

The cron block runs:

- `php commands/world.php process-hour`
- `php commands/world.php process-day`
- `php commands/world.php process-week`
- `php commands/dirty-jobs.php expire`
- `php commands/dirty-jobs.php refresh`

`process-hour`, `process-day`, and `process-week` now also refresh expired Street Job opportunities and expired recruitment candidates that are not attached to an active crew record.

## Default development administrator

```text
Email: admin@criminal.test
Password: password
```

Create a separate normal account to test the intended tutorial and $500 starting state.

## Useful commands

Run these from `backend`:

```bash
php commands/world.php status
php commands/world.php process-hour
php commands/world.php process-day
php commands/world.php process-week
php commands/world.php process-year
php commands/dirty-jobs.php status
php commands/dirty-jobs.php refresh
php commands/warehouse.php status
php commands/economy.php
php commands/crew-portraits.php status
php commands/crew-portraits.php validate
```

Development-only tutorial reset:

```bash
php commands/tutorial.php reset player@example.com
```

## Tests

```bash
php tests/v03_unit.php
php tests/v03_contract.php
php tests/v03_mysql_integration.php
php tests/v035_unit.php
php tests/v035_contract.php
```

The MySQL integration test intentionally refuses to run against a database whose name does not end in `_test`.

Frontend verification:

```bash
cd ../frontend
npm run build
```

## Documentation

- [`docs/INSTALL_NATIVE_MYSQL.md`](docs/INSTALL_NATIVE_MYSQL.md)
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- [`docs/V03_DIRTY_JOBS.md`](docs/V03_DIRTY_JOBS.md)
- [`docs/V035_CREW_PORTRAITS.md`](docs/V035_CREW_PORTRAITS.md)
- [`backend/docs-api.md`](backend/docs-api.md)
- [`docs/ER_DIAGRAM.md`](docs/ER_DIAGRAM.md)
- [`docs/VERIFICATION.md`](docs/VERIFICATION.md)
- [`CHANGELOG.md`](CHANGELOG.md)
- [`docs/DEVELOPMENT_LOG.md`](docs/DEVELOPMENT_LOG.md)
- [`docs/ROADMAP.md`](docs/ROADMAP.md)


### Mockup asset application pass

The latest v0.3.6 package also applies the supplied noir UI mockup sheet directly into the running frontend. Weapons, protection gear, tools, vehicles, valuables, documents, crime pictures, dirty job pictures, role icons, navigation icons, risk indicators, Drug Market rows, and Warehouse storage rows now use local image assets through the centralized manifest and mapping helpers.

The new mappings include backend seed aliases such as `Saturday Night Special`, `9mm Pistol`, `Sawed-off Shotgun`, `Cheap Knife`, `Lockpick Set`, `Work Gloves`, `Dark Clothing`, `Basic Protective Vest`, `First-Aid Kit`, seeded drugs, warehouse supplies, stolen goods, and vehicle parts, so database naming differences do not fall back to blank placeholders.

### Real visuals hotfix note

The corrected v0.3.6 package includes both `.webp` files and matching `.svg` wrapper files for the main game assets. Use the `.webp` paths in new code, but the SVG wrappers are kept so older cached Vite output or old components that still request `.svg` paths do not show blank template placeholders.

# Criminal Empire Online v0.3.5 — Crew Portraits & Design Update

Criminal Empire Online is a browser-based, primarily single-player criminal empire simulation. The player begins with $500, performs small NPC-provided jobs, recruits a crew, equips members, plans structured Dirty Jobs, manages heat and consequences, and works toward owning a warehouse.

## Technology

- PHP 8.2+ custom REST API
- PDO with MySQL 8 or MariaDB
- React, TypeScript, and Vite
- Bearer-token authentication
- Raw SQL migrations and seeders

This project is **not Laravel**. Business logic is organized into readable controllers and services under `backend/app`.

## v0.3.5 highlights

- Fifty persistent crew portrait identities extracted from the supplied concept sheets
- Strict gender-compatible assignment: male NPCs receive male portrait sets and female NPCs receive female portrait sets
- Five centralized life stages: Very Young, Young, Adult, Mature, and Elder
- Stable portrait identity across hiring, dismissal, arrest, rehiring, and aging
- Backend-derived portrait metadata in crew and recruitment API responses
- Automatic portrait-stage updates through idempotent game-world year processing
- Safe portrait fallback when a stage asset is not yet available
- Redesigned cinematic Crew and Recruitment pages with responsive cards, filters, sorting, status, traits, equipment, biography, and history
- Dedicated crew profile presentation with finances, condition, loadout, statistics, and timeline
- Developer portrait validation and existing-NPC backfill commands
- Existing v0.3 tutorial, Dirty Jobs, equipment, heat, crew history, and warehouse systems remain intact
- New players still begin with exactly $500; existing balances and crew data are never reset

### Current artwork status

The supplied sheets contain 50 distinct character portraits, so the project currently includes 50 optimized adult-stage WebP assets and 50 thumbnails. The code supports five life-stage files for every identity, but the supplied images do not depict the same 50 people at five different ages. Therefore, no unrelated faces were falsely presented as age progressions. Missing stages safely use the matching identity's adult portrait until final life-stage artwork is added.

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

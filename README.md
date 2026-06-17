# Criminal Empire Online v0.3 — Dirty Jobs Expansion

Criminal Empire Online is a browser-based, primarily single-player criminal empire simulation. The player begins with $500, performs small NPC-provided jobs, recruits a crew, equips members, plans structured Dirty Jobs, manages heat and consequences, and works toward owning a warehouse.

## Technology

- PHP 8.2+ custom REST API
- PDO with MySQL 8 or MariaDB
- React, TypeScript, and Vite
- Bearer-token authentication
- Raw SQL migrations and seeders

This project is **not Laravel**. Business logic is organized into readable controllers and services under `backend/app`.

## v0.3 highlights

- Persistent 10-step new-player tutorial
- NPC-generated Dirty Job opportunities
- Preparation, crew roles, execution, decisions, and five outcome levels
- Crew equipment, structured item effects, slots, durability, damage, and loss
- Crew injuries, arrests, recovery, dismissal, and permanent history
- Purchasable warehouses with item, weapon, drug, and vehicle storage
- Warehouse capacity, security, operating costs, upgrades, and transfer logs
- Abstract, fictional marijuana production operation integrated with warehouse storage
- Heat reduction and expanded world-processing commands
- New players still begin with exactly $500; existing balances are never reset

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
php commands/dirty-jobs.php status
php commands/dirty-jobs.php refresh
php commands/warehouse.php status
php commands/economy.php
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
- [`backend/docs-api.md`](backend/docs-api.md)
- [`docs/ER_DIAGRAM.md`](docs/ER_DIAGRAM.md)
- [`docs/VERIFICATION.md`](docs/VERIFICATION.md)
- [`CHANGELOG.md`](CHANGELOG.md)
- [`docs/DEVELOPMENT_LOG.md`](docs/DEVELOPMENT_LOG.md)
- [`docs/ROADMAP.md`](docs/ROADMAP.md)

# v0.3 verification report

Verification performed on 2026-06-17 for Criminal Empire Online v0.3.0.

## Commands executed successfully

Backend PHP syntax:

```bash
find backend -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Result: 72 PHP files reported no syntax errors.

Service and calculation tests:

```bash
php backend/tests/v03_unit.php
```

Result: 20 passed, 0 failed, 0 skipped.

Source-contract and schema/API tests:

```bash
php backend/tests/v03_contract.php
```

Result: 77 passed, 0 failed, 0 skipped.

Frontend production build:

```bash
cd frontend
npm ci --ignore-scripts
npm run build
```

Result: TypeScript compilation and Vite production build succeeded. Vite transformed 33 modules. npm reported zero vulnerabilities at the time of verification.

## MySQL integration test status

Command attempted:

```bash
php backend/tests/v03_mysql_integration.php
```

Result: 1 skipped. The execution environment did not have the `pdo_mysql` PHP extension or a MySQL server, so the real database integration suite could not run there.

The integration suite is included and guarded. It only recreates a database whose name ends in `_test`. Run it locally with the instructions in `INSTALL_NATIVE_MYSQL.md` before deploying the migration to a production database.

No successful live-MySQL result is claimed in this report.

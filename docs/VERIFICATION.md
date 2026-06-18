# v0.3.5 verification report

Verification performed on 2026-06-18 for Criminal Empire Online v0.3.5 — Crew Portraits & Design Update.

## Commands executed successfully

Backend PHP syntax:

```bash
find backend -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Result: 82 PHP files reported no syntax errors.

Existing v0.3 service and calculation tests:

```bash
php backend/tests/v03_unit.php
```

Result: 20 passed, 0 failed, 0 skipped.

Existing v0.3 source-contract tests:

```bash
php backend/tests/v03_contract.php
```

Result: 77 passed, 0 failed, 0 skipped.

Crew portrait unit tests:

```bash
php backend/tests/v035_unit.php
```

Result: 21 passed, 0 failed, 0 skipped.

Crew portrait source-contract and asset tests:

```bash
php backend/tests/v035_contract.php
```

Result: 27 passed, 0 failed, 0 skipped.

Portrait manifest validation:

```bash
php backend/commands/crew-portraits.php validate
```

Result:

```text
Portrait sets: 50
Complete five-stage sets: 0
Missing stage warnings: 200
Errors: 0
Manifest valid: yes
Manifest complete: no
```

The warnings accurately reflect missing final age-stage artwork. Every supplied adult asset and thumbnail is present and readable.

Frontend production build:

```bash
cd frontend
npm run build
```

Result: TypeScript compilation and Vite production build succeeded. Vite transformed 46 modules.

Image validation:

- 50 full portrait assets opened successfully at 480×600 WebP
- 50 thumbnails opened successfully at 192×240 WebP
- no corrupt portrait files were detected

## MySQL integration test status

Command executed:

```bash
php backend/tests/v03_mysql_integration.php
```

Result: 0 passed, 0 failed, 1 skipped. The execution environment did not have the `pdo_mysql` PHP extension or a MySQL server.

The integration suite is guarded and only recreates a database whose name ends in `_test`. Run it locally after applying migration `004_crew_portraits_design.sql` and before deploying to production.

No successful live-MySQL result is claimed in this report.

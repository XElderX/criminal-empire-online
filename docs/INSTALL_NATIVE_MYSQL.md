# Criminal Empire Online v0.3.5 native installation

This guide installs the project on Ubuntu with MySQL and normal terminal commands. Docker is not required.

## 1. Install system packages

```bash
sudo apt update
sudo apt install -y \
    mysql-server \
    php-cli \
    php-mysql \
    php-mbstring \
    php-curl \
    php-json \
    unzip \
    curl \
    nodejs \
    npm

sudo systemctl enable --now mysql
```

Verify the tools:

```bash
php -v
php -m | grep -i pdo_mysql
mysql --version
node -v
npm -v
```

PHP 8.2 or newer and Node.js 18 or newer are recommended.

## 2. Put the project in place

Example location:

```bash
sudo mkdir -p /var/www
sudo chown -R "$USER":"$USER" /var/www
cd /var/www
unzip criminal-empire-online-v0.3.5-crew-portraits-design.zip
cd criminal-empire-online
```

If the ZIP extracts directly into the project files, simply enter that extracted directory.

## 3. Create the MySQL database and user

Ubuntu commonly authenticates the MySQL root user through the local Unix socket. Use:

```bash
sudo mysql
```

Then execute:

```sql
CREATE DATABASE criminal_empire
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'criminal_user'@'localhost'
    IDENTIFIED BY 'replace_with_a_strong_password';

GRANT ALL PRIVILEGES ON criminal_empire.*
    TO 'criminal_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

## 4. Configure the backend

```bash
cd /var/www/criminal-empire-online/backend
cp .env.example .env
nano .env
```

Example local configuration:

```env
APP_ENV=local
APP_KEY=replace-with-a-long-random-local-key

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=criminal_empire
DB_USERNAME=criminal_user
DB_PASSWORD=replace_with_a_strong_password

CORS_ALLOWED_ORIGIN=http://127.0.0.1:5173
JOB_DURATION_MULTIPLIER=1
```

For shorter development timers:

```env
JOB_DURATION_MULTIPLIER=0.05
```

Do not commit `backend/.env` to Git.

## 5. Run migrations and seeders

From the `backend` directory:

```bash
php database/migrate.php
php commands/crew-portraits.php backfill
php commands/crew-portraits.php validate
```

A successful fresh installation applies:

```text
001_schema.sql
002_single_player_foundation.sql
003_dirty_jobs_expansion.sql
004_crew_portraits_design.sql
001_seed.sql
002_single_player_seed.sql
003_dirty_jobs_seed.sql
004_crew_portraits_seed.sql
```

The v0.3 migration does not reset existing player cash, inventory, crew, or tutorial progress. Existing players are placed in a completed tutorial state so they are not forced through new-player onboarding. Newly registered players receive the active tutorial and exactly $500. The v0.3.5 migration preserves every NPC and crew record; the portrait backfill only assigns identities where `portrait_set_key` is empty.

## 6. Start the PHP API

From `backend`:

```bash
php -S 127.0.0.1:8085 -t public public/index.php
```

Keep this terminal open. The API is available at:

```text
http://127.0.0.1:8085/api
```

Check it by registering or logging in through the frontend. Authenticated `/api/me` responses include the current application version and release title.

## 7. Install and start the frontend

Open another terminal:

```bash
cd /var/www/criminal-empire-online/frontend
npm install
npm run dev -- --host 127.0.0.1
```

Open:

```text
http://127.0.0.1:5173
```

Default seeded administrator:

```text
Email: admin@criminal.test
Password: password
```

Use a newly registered normal account to test the complete tutorial and early-game progression.

## 8. Process the game world manually

Run commands from `backend`.

World status and processing:

```bash
php commands/world.php status
php commands/world.php process-hour
php commands/world.php process-day
php commands/world.php process-week
php commands/world.php process-year
```

Dirty Job opportunity tools:

```bash
php commands/dirty-jobs.php status
php commands/dirty-jobs.php refresh
php commands/dirty-jobs.php expire
```

Warehouse tools:

```bash
php commands/warehouse.php status
php commands/warehouse.php process-costs
```

Economy report:

```bash
php commands/economy.php
```

Crew portrait tools:

```bash
php commands/crew-portraits.php status
php commands/crew-portraits.php backfill
php commands/crew-portraits.php validate
php commands/crew-portraits.php sync-stages
```

Development-only tutorial reset, requiring `APP_ENV=local`:

```bash
php commands/tutorial.php reset player@example.com
```

## 9. Optional cron scheduling

Edit the current user’s cron configuration:

```bash
crontab -e
```

Example schedule:

```cron
0 * * * * cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-hour >> /tmp/criminal-world-hour.log 2>&1
5 0 * * * cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-day >> /tmp/criminal-world-day.log 2>&1
10 0 * * 1 cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-week >> /tmp/criminal-world-week.log 2>&1
```

Do not process the economy from page rendering. Use these commands or another scheduler.

## 10. Run tests

Static and service-level tests do not modify the normal game database:

```bash
cd /var/www/criminal-empire-online/backend
php tests/v03_unit.php
php tests/v03_contract.php
php tests/v035_unit.php
php tests/v035_contract.php
```

For the real MySQL integration test, create a dedicated database whose name ends in `_test`:

```bash
sudo mysql
```

```sql
CREATE DATABASE criminal_empire_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON criminal_empire_test.*
    TO 'criminal_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

Run the integration test with temporary environment variables:

```bash
TEST_DB_DATABASE=criminal_empire_test \
TEST_DB_USERNAME=criminal_user \
TEST_DB_PASSWORD=replace_with_a_strong_password \
php tests/v03_mysql_integration.php
```

The test drops and recreates its configured test database. It refuses to run unless the database name ends in `_test`.

Frontend verification:

```bash
cd /var/www/criminal-empire-online/frontend
npm install
npm run build
```

## 11. Fresh development reset

This destroys all local game data:

```bash
sudo mysql -e "DROP DATABASE IF EXISTS criminal_empire; CREATE DATABASE criminal_empire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON criminal_empire.* TO 'criminal_user'@'localhost'; FLUSH PRIVILEGES;"

cd /var/www/criminal-empire-online/backend
php database/migrate.php
```

## Common problems

### MySQL root access denied

Use:

```bash
sudo mysql
```

Do not use `mysql -u root -p` unless you explicitly configured password authentication for MySQL root.

### `could not find driver`

Install the PHP MySQL extension and confirm it is loaded:

```bash
sudo apt install -y php-mysql
php -m | grep -i pdo_mysql
```

Restart a web server or PHP-FPM service if you use one. The built-in PHP server only needs to be restarted.

### Frontend cannot reach the API

Confirm:

```text
API:      http://127.0.0.1:8085
Frontend: http://127.0.0.1:5173
```

And ensure `backend/.env` has:

```env
CORS_ALLOWED_ORIGIN=http://127.0.0.1:5173
```

Use the same hostname in both places. `localhost` and `127.0.0.1` are different origins to a browser.

### Migration fails halfway

Read the first SQL error, correct the database state, then use a fresh local database during development. Raw SQL migration files are intentionally explicit and are not Laravel migrations.

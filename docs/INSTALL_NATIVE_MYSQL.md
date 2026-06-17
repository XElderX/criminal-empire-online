# Native installation (Ubuntu + MySQL, no Docker)

## 1. Install requirements

```bash
sudo apt update
sudo apt install -y mysql-server php-cli php-mysql php-mbstring php-curl unzip curl nodejs npm
sudo systemctl enable --now mysql
php -v
mysql --version
node -v
npm -v
```

PHP 8.2+ and Node.js 18+ are recommended.

## 2. Open the project

```bash
cd /var/www/criminal-empire-online
```

## 3. Create a MySQL database and user

On Ubuntu, log in through the local administrative socket:

```bash
sudo mysql
```

Then run:

```sql
CREATE DATABASE criminal_empire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'criminal_user'@'localhost' IDENTIFIED BY 'change_this_password';
GRANT ALL PRIVILEGES ON criminal_empire.* TO 'criminal_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

This avoids the common `Access denied for user 'root'@'localhost'` error caused by Ubuntu's socket-authenticated MySQL root account.

## 4. Configure the backend

```bash
cd backend
cp .env.example .env
nano .env
```

Use:

```env
APP_ENV=local
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=criminal_empire
DB_USERNAME=criminal_user
DB_PASSWORD=change_this_password
CORS_ALLOWED_ORIGIN=http://localhost:5173
JOB_DURATION_MULTIPLIER=1
```

For fast development timers, use:

```env
JOB_DURATION_MULTIPLIER=0.05
```

## 5. Run migrations and seed data

From the `backend` directory:

```bash
php database/migrate.php
```

Expected final output:

```text
Migrated: .../001_schema.sql
Migrated: .../002_single_player_foundation.sql
Seeded: .../001_seed.sql
Seeded: .../002_single_player_seed.sql
Done.
```

The migration does not reset balances for existing users. The new $500 starting balance applies only to newly registered players.

For a completely fresh development reset:

```bash
sudo mysql -e "DROP DATABASE IF EXISTS criminal_empire; CREATE DATABASE criminal_empire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON criminal_empire.* TO 'criminal_user'@'localhost'; FLUSH PRIVILEGES;"
php database/migrate.php
```

## 6. Start the PHP API

From `backend`:

```bash
php -S 127.0.0.1:8085 -t public public/index.php
```

Keep that terminal open. The API will be available at:

```text
http://127.0.0.1:8085/api
```

## 7. Install and start the React frontend

Open a second terminal:

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

Create a new normal account to test the intended single-player start with exactly `$500`.

## 8. World and economy commands

From `backend`:

```bash
php commands/world.php status
php commands/world.php process-hour
php commands/world.php process-day
php commands/world.php process-week
php commands/economy.php
```

`process-week` handles due gang-member salary payments. Re-running it immediately does not pay the same weekly salary twice because each member's `last_salary_at` is updated.

## 9. Optional automatic processing with cron

```bash
crontab -e
```

Add:

```cron
0 * * * * cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-hour >> /tmp/criminal-world-hour.log 2>&1
5 0 * * * cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-day >> /tmp/criminal-world-day.log 2>&1
10 0 * * 1 cd /var/www/criminal-empire-online/backend && /usr/bin/php commands/world.php process-week >> /tmp/criminal-world-week.log 2>&1
```

## Common fixes

### MySQL root access denied

Use:

```bash
sudo mysql
```

Do not use `mysql -u root -p` unless you explicitly configured a root password authentication method.

### `could not find driver`

```bash
sudo apt install -y php-mysql
php -m | grep -i pdo_mysql
```

### Frontend cannot reach API

Confirm the API is running on port `8085` and `backend/.env` contains:

```env
CORS_ALLOWED_ORIGIN=http://localhost:5173
```

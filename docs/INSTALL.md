# Installation Guide

## Requirements

- PHP 8.2+ with PDO MySQL
- Node.js 20+
- MySQL or MariaDB
- Git

No Docker is required for the native setup.

## Setup

```bash
unzip criminal-empire-online.zip
cd criminal-empire-online
cp backend/.env.example backend/.env
```

Edit `backend/.env` to match your local MySQL credentials. For the setup you described, use:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=criminal_emire
DB_USERNAME=dalius
DB_PASSWORD=qwertyui
```

Create the database if it does not already exist:

```sql
CREATE DATABASE criminal_emire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run migrations and seeders:

```bash
php backend/database/migrate.php
```

Start the backend API:

```bash
php -S 127.0.0.1:8085 -t backend/public backend/public/index.php
```

In a second terminal, start the frontend:

```bash
cd frontend
npm install
npm run dev
```

Open:

```text
Frontend: http://localhost:5173
Backend:  http://localhost:8085/api
```

Default admin login:

```text
email: admin@criminal.test
password: password
```

## Useful Commands

```bash
php backend/database/migrate.php
mysql -udalius -pqwertyui criminal_emire
```

## Troubleshooting

If `npm install` fails with `EACCES` inside `frontend/node_modules`, the folder was likely created by another user or by Docker. Fix the ownership and try again:

```bash
sudo chown -R "$USER:$USER" frontend/node_modules
npm install
```

If the Vite dev server reports that port `5173` is already in use, stop the old process and start again:

```bash
pkill -f "vite"
npm run dev
```

## API Test

```bash
curl -X POST http://localhost:8085/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@criminal.test","password":"password"}'
```

Copy the token and call:

```bash
curl http://localhost:8085/api/crimes \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## Notes

This is v0.1 MVP, not a finished production MMO. It is intentionally modular so new systems can be added:

- gang wars
- territory attack resolution
- dynamic market tick scheduler
- prison system
- political elections
- government AI jobs
- marketplace buying/selling

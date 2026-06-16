# Criminal Empire Online v0.1

Browser-based multiplayer crime strategy MVP inspired by classic Mafia Wars/Mob Wars.

This first version includes:

- PHP 8 REST API backend
- MySQL/MariaDB database
- React + Vite frontend
- Native local setup
- Secure registration/login with token auth
- Crimes, energy, heat, XP and cash
- Weapons and inventory
- Drug market with regional prices
- Businesses with hourly income
- Gangs and gang membership
- Territories and simple territory attacks
- Corruption/bribery system
- Basic government AI tick
- Admin panel API endpoints
- Installation guide

See `docs/INSTALL.md`.

## Quick start

```bash
cp backend/.env.example backend/.env
php backend/database/migrate.php
php -S 127.0.0.1:8085 -t backend/public backend/public/index.php
```

Then open:

- Frontend: http://localhost:5173
- Backend API: http://localhost:8085/api

Default admin:

```text
email: admin@criminal.test
password: password
```

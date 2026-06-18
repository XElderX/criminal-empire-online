# Installation

The supported local installation for Criminal Empire Online v0.3.6 uses native PHP, MySQL, Node.js, and npm. Docker is not required.

Follow the complete guide:

- [`INSTALL_NATIVE_MYSQL.md`](INSTALL_NATIVE_MYSQL.md)

The essential order is:

1. Install PHP with `pdo_mysql`, MySQL, Node.js, and npm.
2. Create a MySQL database and a dedicated database user.
3. Copy `backend/.env.example` to `backend/.env`.
4. Run `php database/migrate.php` from `backend`.
5. Start the PHP API on port `8085`.
6. Install frontend dependencies and start Vite on port `5173`.

Do not run Laravel commands such as `php artisan`; this project uses a lightweight custom PHP API.

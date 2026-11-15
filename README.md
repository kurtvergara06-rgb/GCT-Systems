<p align="center">
  <img src="https://laravel.com/img/logomark.min.svg" width="120" alt="Laravel Logo">
</p>

# GCT Shuttle Services Management System

This system is a Laravel-based fleet management system for GCT Shuttle Services. It includes modules for Maintenance, Warehouse, Purchase, and Operations.

## System Requirements

Before installing the system, make sure the following are installed on your computer:

- PHP 8.3 or higher
- Composer
- Node.js and NPM
- MySQL / XAMPP
- Git
- Laravel Herd, Laragon, XAMPP, or any local server

## How to Install

1. Clone the repository.

```bash
git clone <repository-url>
cd gct_system
```

2. Install PHP dependencies.

```bash
composer install
```

3. Install JavaScript dependencies.

```bash
npm install
```

4. Copy the environment file.

```bash
cp .env.example .env
```

On Windows PowerShell, you can use:

```powershell
Copy-Item .env.example .env
```

5. Generate the Laravel application key.

```bash
php artisan key:generate
```

6. Configure the database connection in the `.env` file.

7. Run the database migrations.

```bash
php artisan migrate
```

8. Start the Vite development server.

```bash
npm run dev
```

9. Start the Laravel development server.

```bash
php artisan serve
```

## Database Setup

Create a MySQL database named `gct_system`, then update your `.env` file with the database configuration below:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=gct_system
DB_USERNAME=root
DB_PASSWORD=
```

If your MySQL server uses the default port, change `DB_PORT` to `3306`.

After saving the `.env` file, run:

```bash
php artisan migrate
```

## How to Run the System

Open two terminal windows in the project folder.

In the first terminal, run:

```bash
npm run dev
```

In the second terminal, run:

```bash
php artisan serve
```

After the Laravel server starts, open the local URL shown in the terminal. The default is usually:

```text
http://127.0.0.1:8000
```

## Default URLs / Pages

Common local pages may include:

- Main application: `http://127.0.0.1:8000`
- Login page: `http://127.0.0.1:8000/login`
- Maintenance module pages
- Warehouse module pages
- Purchase module pages
- Operations module pages

Actual page URLs may vary depending on the routes configured in the Laravel application.

To view all available routes, run:

```bash
php artisan route:list
```

## Troubleshooting

### MySQL Port Issue

If Laravel cannot connect to MySQL, check the MySQL port used by your local server.

- Use `DB_PORT=3307` if your MySQL server runs on port `3307`.
- Use `DB_PORT=3306` if your MySQL server uses the default MySQL port.

After updating `.env`, clear the configuration cache:

```bash
php artisan config:clear
```

### Composer Install Error

If `composer install` fails, make sure Composer and PHP are installed correctly:

```bash
composer --version
php -v
```

Then try running:

```bash
composer install
```

If the error mentions missing PHP extensions, enable the required extensions in your PHP configuration.

### NPM Error

If `npm install` or `npm run dev` fails, check that Node.js and NPM are installed:

```bash
node -v
npm -v
```

You can also try reinstalling the dependencies:

```bash
npm install
```

### Migration Error

If `php artisan migrate` fails, confirm that:

- MySQL is running.
- The database `gct_system` exists.
- The `.env` database username, password, host, and port are correct.

Then clear the configuration cache and try again:

```bash
php artisan config:clear
php artisan migrate
```

### Laravel Key Error

If you see an application key error, generate a new Laravel key:

```bash
php artisan key:generate
```

Then clear the configuration cache:

```bash
php artisan config:clear
```

### Page Not Found / Route Error

If a page shows a `404 Not Found` error, check the available routes:

```bash
php artisan route:list
```

Make sure the URL matches a route defined in the application. If routes were recently changed, clear cached routes:

```bash
php artisan route:clear
```

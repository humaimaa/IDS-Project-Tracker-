# IDS Project Tracker

Laravel 13 front-end prototype for the International Development Section project tracker. The dashboard follows the supplied teal overview reference and uses Tailwind CSS v4 through Vite.

## Run locally

Requirements: PHP 8.3+, Composer, and Node.js with npm.

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Start the Laravel app and Vite in separate terminals:

```sh
php artisan serve
npm run dev
```

Open the URL printed by `php artisan serve` (normally `http://127.0.0.1:8000`). For a production asset build, run `npm run build`.

## Prototype scope

The dashboard uses static PHP demo data from `config/dashboard.php`. Management screens persist records in the database and validate form submissions. Account setup, login, and account updates are implemented. Project-level access restrictions, role-based authorization, and report exports remain prototype limitations.

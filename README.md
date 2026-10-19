# Project Subaybay migration

The repository is organized into a React/Vite frontend in `frontend/` and a Laravel 12 API in `backend/`. Original PHP page files, assets, and uploads are grouped under `frontend/legacy-php/` as a compatibility/reference copy. Their shared database configuration/helpers are under `backend/legacy-php/`. The React app reuses the original stylesheet and browser script from `frontend/public/assets/`.

```text
Reading/
|- frontend/
|  |- src/                 React application
|  |- public/assets/       Assets used by React
|  `- legacy-php/          Preserved PHP pages, legacy assets, and uploads
|- backend/
|  |- app/, routes/, ...   Laravel API
|  `- legacy-php/          Legacy shared PHP configuration/helpers
|- README.md
`- .gitignore
```

## Local development

Requirements: PHP 8.2+, Composer 2, Node.js 22+, npm, and a MySQL-compatible database. The current local `backend/.env` connects Laravel to XAMPP MariaDB at `127.0.0.1:3306`, database `subaybay`. PHPUnit tests use isolated SQLite.

1. In `backend/`, set `.env` to your local database, run `composer install` if `vendor/` is absent, then `php artisan key:generate` if `APP_KEY` is blank.
2. `database/schema.sql` creates and selects the local database named `subaybay`, creates its tables, and imports the records from the supplied SQL dump. Import it only for a new or empty database; it is not an update script for a database that already has these tables.
3. In this checkout, Laravel connects to the imported XAMPP `subaybay` database. The available Laravel migrations are marked as run; PHPUnit uses isolated SQLite instead.
4. Run `php artisan serve` in `backend/`.
5. In `frontend/`, run `npm ci`, copy `.env.example` to `.env.local`, set `VITE_API_BASE_URL=http://127.0.0.1:8000/api`, and run `npm run dev`.

The API authenticates with bearer tokens using Laravel Sanctum. The local XAMPP MariaDB schema was inspected and missing Laravel fields were added with non-destructive migrations. No remote production database has been configured or changed.

## Deploy the frontend to Vercel

`frontend/vercel.json` records the Vite framework, `npm run build`, `dist` output, and SPA route fallback. In Vercel, create a project from this repository and set its **Root Directory** to `frontend`. Set `VITE_API_BASE_URL` in the Production environment to the deployed Render API URL ending in `/api` (for example, `https://subaybay-api.onrender.com/api`). Set the same variable for Preview deployments if you want previews to call a non-production API. Vite embeds this public URL in the browser build; never put server secrets in a `VITE_` variable.

The source must be available from a Git provider or a Vercel CLI deployment before Vercel can build it. The API URL in the example must be replaced if Render assigns a different service domain.

## Deploy the backend to Render

The root `render.yaml` defines a Render **Web Service** using Docker, `backend/` as its root, the existing Dockerfile, `/up` as its health check, and a 1 GB persistent disk mounted at `/var/data` for Laravel's local uploads. The service is set to the `starter` plan because Render persistent disks require a paid service. Review the plan and disk size in Render before syncing the Blueprint; increasing disk size later is possible, decreasing it is not.

During the initial Blueprint setup, provide the values marked `sync: false`:

- `APP_KEY`: generate a new key with `php artisan key:generate --show` from `backend/`; use this production key only for this deployment and keep it private.
- `APP_URL`: the final Render service URL.
- `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`: credentials for the MySQL-compatible database you choose. The Blueprint defaults to port `3306`; change it if your database provider uses another port. These values are required for the Laravel data API to work, but no database is provisioned by this Blueprint.
- `FRONTEND_ORIGINS`: the exact deployed Vercel origin, such as `https://your-project.vercel.app` (no trailing slash). Add other trusted origins as a comma-separated list.

If your chosen database requires TLS, configure `MYSQL_ATTR_SSL_CA` in Render and provide its CA file as a Render Secret File at `/etc/secrets/<filename>`. The container supports provider-specific TLS certificates without requiring any particular database service.

The entrypoint assigns the mounted upload directory to the Apache/PHP user. New profile images, learning materials, endorsement documents, and generated reports are written under `/var/data`. The existing files under `frontend/legacy-php/uploads/` are not copied automatically. Before switching users to production, migrate the needed files to the persistent disk while preserving the relative paths referenced by the database, then verify authenticated file downloads. Back up the chosen database and existing upload files separately.

Do not add a deploy-time `php artisan migrate --force` command until the target database schema has been inspected and a reviewed, backward-compatible migration plan is approved. The current health endpoint checks that Laravel serves HTTP; it does not prove that the database is reachable.

## Data and migration status

No remote database connection was made. Do not run schema migrations until the live schema is inspected, compared to the Laravel migration, and backed up. The Laravel migration creates missing tables, but some legacy column differences and constraints may require a reviewed compatibility migration. The legacy application initializes/updates its schema and demo settings from `backend/legacy-php/config.php`; do not point it at production data during migration checks.

The deployment files and local builds do not constitute a live deployment. Before production use, choose and configure a reachable MySQL-compatible database, verify the schema against it, confirm every required workflow against real API data, migrate existing uploads, configure Vercel and Render environment variables, and perform browser smoke tests against the deployed services. The hosting setup does not provision or assume a specific database provider. The Docker image could not be built in this Windows environment because Docker is unavailable.

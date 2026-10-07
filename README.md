# Movie Night

Employee movie-night registration with assigned halls and shifts, seat selection, and an authenticated admin panel. The application currently uses PHP and MySQL/MariaDB. Firebase migration is planned separately.

## Run locally

Requires PHP 8.2 with PDO MySQL, mbstring, and fileinfo; MySQL/MariaDB; and Node.js for JavaScript regression checks. XAMPP provides the PHP and database runtime.

From the project folder in PowerShell, with your database running:

```powershell
.\scripts\start-dev.ps1
```

Open http://127.0.0.1:8080. The server serves only the public folder. For Apache/XAMPP, set the virtual host DocumentRoot to this project's public directory. The root Apache rules redirect directory visits into public and deny access to the private project files.

Database defaults are localhost:3306, movie_night_db, root, and an empty password. Override DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASS through environment variables. For example:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '33079'
.\scripts\start-dev.ps1
```

The existing database continues to work. database/schema.sql contains table definitions for an empty installation; database/backups preserves the original snapshot. Schema-only installations need event settings, employee records, halls/shifts/seats, and a password-hashed admin account before normal use.

PHP sessions and logs default to storage/sessions and storage/logs. These folders must be writable by PHP and are outside the web root. APP_SESSION_PATH and APP_LOG_PATH override their locations. APP_DEBUG=1 enables local error display.

## Layout

- public: existing page/API URLs and browser assets, grouped into css, js, and images.
- app/controllers: request validation, authorization, orchestration, and page queries.
- app/views: PHP templates with small JSON configuration blocks for browser scripts.
- app/services: booking rules and shared employee creation.
- app/repositories: the transactional MySQL booking adapter.
- app/support: database, security, authentication, and logging helpers loaded by app/bootstrap.php.
- database: schema and the preserved database snapshot.
- storage: private generated sessions and logs; contents are excluded from Git.
- tests: service, endpoint, database-concurrency, seat-selection, and admin UI regression checks.
- scripts: PowerShell development commands.
- docs: project overview, admin guide, and Firebase migration notes; historical analysis lives in docs/archive.

## Validate

```powershell
.\tests\run-tests.ps1
```

The runner uses an isolated database on port 33079 and refuses an occupied port. Stop a local preview using that port before running the full runner. See [tests/README.md](tests/README.md) for coverage and individual checks.

See [the project overview](docs/PROJECT_OVERVIEW.md) for request flow and cleanup details.

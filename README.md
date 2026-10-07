# Pet Care Tracker

A free, open-source application for keeping track of a pet's care and health history, especially when managing a chronic condition.

Planned features include recording meals, medication administration, symptoms, and measurements, and reviewing that history to support communication with a veterinarian. Core tracking and health-history features will remain free. The application will not diagnose conditions or recommend treatment or medication doses.

**Status:** early development. The Laravel API foundation runs locally in DDEV. The React frontend and product features are not initialized yet.

## Stack

- Backend: Laravel 13 REST API, Sanctum, and MariaDB.
- Frontend: React and TypeScript.
- Local development: DDEV with PHP 8.4, MariaDB 11.8, Composer 2, Node.js 24, and phpMyAdmin.

## Local development

Install Docker and DDEV, then run from the repository root:

```sh
ddev start
ddev composer install
cp backend/.env.example backend/.env
ddev dotenv set backend/.env --db-password=db
ddev php artisan key:generate
ddev setup-test-db
ddev php artisan migrate
ddev describe
```

These setup commands are for a fresh checkout. Keep existing environment files and application keys when returning to the project; use `ddev start` to resume development.

The local application address is `https://pet-care-tracker.ddev.site`. Use `ddev describe` for the actual URLs and ports. The framework health route is `/up`; it checks application startup, not database readiness. `/api/user` returns a JSON `401` response until authentication is implemented and the client is authenticated.

DDEV serves `backend/public` and runs shell and Composer commands in `backend/`.

```sh
ddev ssh                  # Open a shell in backend/
ddev composer --version
ddev mysql                # Open the database shell
ddev phpmyadmin           # Open phpMyAdmin
ddev stop                 # Stop the project
```

Inside the web container, MariaDB uses host `db`, port `3306`, and database, username, and password `db`. These are local development defaults.

DDEV's automatic framework settings management is disabled. Application settings belong in `backend/.env`. Real environment files are ignored by Git; the example files have empty application keys and database passwords.

The backend is served by DDEV; no backend Node build or separate PHP development server is required for these routes. User login and registration have not been implemented.

## Backend checks

Run from the repository root:

```sh
ddev composer test
ddev composer lint
ddev composer validate --strict
ddev composer check-platform-reqs
```

`ddev setup-test-db` creates the local `db_test` database, a user restricted to that database, and an ignored `backend/.env.testing` if it does not already exist. Its local username and password are both `db_test`. Tests use MariaDB and refuse to run application tests with a different database or user. They can reset the test database; keep it disposable.

Do not use DDEV's development or test credentials in a deployment. Frontend check commands will be added when React is initialized.

## Application directories

- `backend/`: Laravel application, API routes, migrations, and tests.
- `frontend/`: intended React application location; not created yet.
- `.ddev/`: local development configuration, including phpMyAdmin.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for a short contribution guide. Use synthetic example data and keep credentials and real pet records out of the repository.

## License

See [LICENSE](LICENSE) for the GNU GPL version 3 license text.

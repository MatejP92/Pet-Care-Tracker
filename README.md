# Pet Care Tracker

A free, open-source application for keeping track of a pet's care and health history, especially when managing a chronic condition.

Planned features include recording meals, medication administration, symptoms, and measurements, and reviewing that history to support communication with a veterinarian. Core tracking and health-history features will remain free. The application will not diagnose conditions or recommend treatment or medication doses.

**Status:** early development. Laravel and React run locally in DDEV, with a status page that checks API connectivity. Product features are not implemented yet.

## Stack

- Backend: Laravel 13 REST API, Sanctum, and MariaDB.
- Frontend: React 19, TypeScript 6, and Vite 8.
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
ddev frontend ci
ddev describe
```

These setup commands are for a fresh checkout. Keep existing environment files and application keys when returning to the project; use `ddev start` to resume development.

The local API address is `https://pet-care-tracker.ddev.site`. Use `ddev describe` for the actual URLs and ports. The framework health route is `/up`; it checks application startup. Public `GET /api/health` returns HTTP `200` and exactly `{"status":"ok"}`, with caching disabled. Neither health endpoint checks database readiness. `/api/user` returns a JSON `401` response until authentication is implemented and the client is authenticated.

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

## React development

Start DDEV, then keep the frontend development server running in a terminal:

```sh
ddev frontend run dev
```

Open `https://pet-care-tracker.ddev.site:5173`. Stop Vite with Ctrl+C; use `ddev start` and the same command when resuming development. If adding the frontend configuration to an already running checkout, run `ddev restart` once to expose its port.

`ddev frontend` runs npm in `frontend/`, using DDEV's Node.js 24 and npm 11. Dependencies are locked in `frontend/package-lock.json`; install them with `ddev frontend ci` after a fresh checkout or a lockfile change.

The status page displays loading, connected, and failure states, with a retry action and a ten-second timeout. Requests use `/api/health`; Vite proxies `/api` to Laravel inside the web container. The browser uses the frontend origin, so this check needs no additional Laravel CORS configuration.

`frontend/.env.example` documents the public `VITE_API_BASE_URL` build setting, which defaults to `/api`. Copy it to `frontend/.env.local` only to override that default. All `VITE_*` values are public in the browser; never place secrets there. A cross-origin API override needs an explicit backend CORS policy. Deployment routing and authentication settings are not configured by this development proxy.

## Frontend checks

Run from the repository root:

```sh
ddev frontend run lint
ddev frontend run typecheck
ddev frontend run build
```

The production build is written to the ignored `frontend/dist/` directory. Building does not deploy it or configure an API proxy; deployment must route `/api` to Laravel or provide a suitable public API base URL.

## Backend checks

Run from the repository root:

```sh
ddev composer test
ddev composer lint
ddev composer validate --strict
ddev composer check-platform-reqs
```

`ddev setup-test-db` creates the local `db_test` database, a user restricted to that database, and an ignored `backend/.env.testing` if it does not already exist. Its local username and password are both `db_test`. Tests use MariaDB and refuse to run application tests with a different database or user. They can reset the test database; keep it disposable.

Do not use DDEV's development or test credentials in a deployment.

## Application directories

- `backend/`: Laravel application, API routes, migrations, and tests.
- `frontend/`: React status page and Vite/TypeScript configuration.
- `.ddev/`: local development configuration, including phpMyAdmin.

## Continuous integration

The `CI` GitHub Actions workflow runs on pull requests targeting `dev` or `main` and pushes to either branch. Its independent backend and frontend jobs run the same Composer and npm checks documented above, installing from the committed lockfiles. The `Branch flow` check requires pull requests targeting `main` to originate from this repository's `dev` branch. Changing a pull request's base branch also reruns the workflow.

The backend job uses PHP 8.4, Composer 2, and a disposable MariaDB 11.8 service. Its `db_test` user can access only the test database; a separate empty `db.users` table lets the existing isolation test verify that access is denied. CI generates its own test application key and uses disposable database credentials, with no production secrets required.

Tests default to DDEV's `db` hostname. CI overrides only `DB_HOST` to connect through the service's mapped localhost port; PHPUnit continues to enforce the test database, test username, and MariaDB connection. The frontend job uses Node.js 24 and runs lint, typecheck, and build. Both jobs use read-only repository permissions.

View results in the pull request's checks or the repository's Actions tab. Both `dev` and `main` are protected by active GitHub rulesets requiring a pull request, an up-to-date branch, resolved review conversations, and successful `Branch flow`, `Backend checks`, and `Frontend checks` from GitHub Actions. Direct pushes, force pushes, and branch deletion are blocked, including for administrators. Approval by a second person is not required.

## Branch workflow

`dev` is the default integration branch; `main` holds releases. Start a short `feat/<description>` branch from the latest `dev` and open a pull request targeting `dev`.

Promote tested changes with a `dev` → `main` pull request. Both branches require fresh passing checks. Use a merge commit for promotion; the `main` ruleset allows only this merge method to preserve shared history. Feature pull requests into `dev` may use squash or merge commits.

After promotion, open a `main` → `dev` synchronization pull request and merge it with a merge commit before the next release. Avoid squash merging between these long-lived branches because it breaks their shared ancestry.

These branches do not create deployed environments. A future test environment can follow `dev`, and production can follow `main`, with separate databases, credentials, and deployment configuration.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for a short contribution guide. Use synthetic example data and keep credentials and real pet records out of the repository.

## License

See [LICENSE](LICENSE) for the GNU GPL version 3 license text.

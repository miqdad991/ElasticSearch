# Deployment Runbook — opensearch2

Laravel app backed by PostgreSQL 16 and OpenSearch 2.18, orchestrated with Docker Compose. All commands assume the repo root is the working directory.

## 1. Prerequisites

- Docker Engine 24+ and the Compose v2 plugin (`docker compose ...`, not `docker-compose`).
- Ports `8080` (nginx), `5432` (postgres), `9200` (opensearch) free on the host — or remap them in `docker-compose.yml`.
- Outbound network access for the initial image pulls and `composer install`.

## 2. Configure environment

```bash
cp .env.docker .env
```

Then edit `.env` and set at minimum, for a server deployment:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<your-domain>`
- `APP_KEY=` — leave blank; generate it in step 4.
- `DB_PASSWORD=` — set to a strong value. Must match `POSTGRES_PASSWORD` in `docker-compose.yml` (change both or parameterise).
- `OSOOL_HMAC_SECRET=` — rotate away from the repo default.

Everything else (`DB_HOST=postgres`, `OPENSEARCH_HOST=opensearch`, ...) can stay as shipped — those are the Docker service names.

## 3. Bring the stack up

```bash
docker compose build
docker compose up -d
```

Wait for the healthchecks to go green before continuing:

```bash
docker compose ps
# postgres and opensearch should be "healthy"
```

## 4. First-run application setup

```bash
# Install PHP deps (incl. dev — Pail is auto-discovered at boot)
docker compose exec app composer install

# Generate APP_KEY if .env left it blank
docker compose exec app php artisan key:generate --force

# Create the schema
docker compose exec app php artisan migrate --force
```

## 5. Create OpenSearch indexes

Each entity gets a timestamped index and an alias (`osool_<entity>`). Running the command is safe on an empty DB — it creates the alias so queries return 0 hits instead of a 404.

```bash
for e in work_orders properties assets users commercial_contracts installments contracts projects; do
  docker compose exec app php artisan os:reindex "$e"
done
```

Seed the calendar dimension (used by DWH reporting):

```bash
docker compose exec app php artisan dwh:seed-calendar
```

## 6. Verify

```bash
# App reachable via nginx
curl -I http://localhost:8080

# OpenSearch aliases exist (expect 8 osool_* entries)
curl http://localhost:9200/_cat/aliases?v

# Postgres is up
docker compose exec postgres pg_isready -U postgres
```

## 7. Common operations

```bash
docker compose logs -f app              # tail app logs
docker compose logs -f opensearch       # tail opensearch logs
docker compose restart app              # restart app only
docker compose down                     # stop & remove containers (volumes preserved)
docker compose down -v                  # stop & remove containers AND volumes (DESTRUCTIVE)
```

### Updating to a new code revision

```bash
git pull
docker compose build app
docker compose up -d
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

Re-run `os:reindex <entity>` for any entity whose mapping changed in the release.

## 8. Staging / QA fake data (optional — do NOT run in production)

Populates ~500 work orders and supporting DWH rows for testing:

```bash
docker compose exec app php artisan db:seed --class=DwhFakeSeeder
docker compose exec app php artisan db:seed --class=DwhFakeAssetsSeeder
docker compose exec app php artisan db:seed --class=DwhFakeContractsSeeder
docker compose exec app php artisan db:seed --class=DwhFakeExecutionContractsSeeder
docker compose exec app php artisan db:seed --class=DwhFakeInstallmentsSeeder
docker compose exec app php artisan db:seed --class=DwhFakeOverviewSeeder

for e in work_orders properties assets users commercial_contracts installments contracts projects; do
  docker compose exec app php artisan os:reindex "$e"
done
```

`DwhFakeSeeder` must run first — it seeds the dimensions the other seeders reference as FKs.

## 9. Troubleshooting

**OpenSearch container exits with `OPENSEARCH_INITIAL_ADMIN_PASSWORD` error.**
The `docker-compose.yml` in this repo sets `DISABLE_INSTALL_DEMO_CONFIG=true` and `plugins.security.disabled=true`. If you change either, you must provide `OPENSEARCH_INITIAL_ADMIN_PASSWORD` (16+ chars, mixed case, digit, symbol).

**`Class "Laravel\Pail\PailServiceProvider" not found` on artisan commands.**
Dev deps missing inside the container. Run `docker compose exec app composer install` (step 4).

**`index_not_found_exception` / 404 from a search endpoint.**
The alias for that entity was never created. Run `docker compose exec app php artisan os:reindex <entity>` — see the full list in step 5.

**Changes to env vars in `docker-compose.yml` don't take effect.**
`docker compose start` doesn't recreate containers. Use `docker compose up -d` (it recreates services whose config changed).

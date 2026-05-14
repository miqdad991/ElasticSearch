# Deployment Commands

## 1. Initial Setup

```bash
# Clone and enter the project
git clone <repo-url>
cd opensearch2
cp .env.example .env
```

Update `.env` with Docker service values:
```
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=opensearch2
DB_USERNAME=postgres
DB_PASSWORD=secret
OPENSEARCH_HOST=opensearch
OPENSEARCH_PORT=9200
OPENSEARCH_SCHEME=http
```

```bash
# Build and start all containers
docker-compose up -d --build

# Generate app key
docker exec opensearch2-app php artisan key:generate

# Run migrations
docker exec opensearch2-app php artisan migrate

# Seed the calendar dimension table (required before loading data)
docker exec opensearch2-app php artisan dwh:seed-calendar
```

---

## 2. Seed Demo / Test Data (optional)

Only needed if you want fake data instead of pulling from the API.

```bash
docker exec opensearch2-app php artisan db:seed --class=DatabaseSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeContractsSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeAssetsSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeInstallmentsSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeExecutionContractsSeeder
docker exec opensearch2-app php artisan db:seed --class=DwhFakeOverviewSeeder
```

After seeding fake data, reindex OpenSearch (see Section 4).

---

## 3. Load Real Data from the API

### Option A — Full cycle in one command (recommended)

Runs all resources in the correct dependency order automatically:

```bash
docker exec opensearch2-app php artisan sync:cycle
```

### Option B — Resource by resource (if you need to run selectively)

Run in this order — each command pulls from the API, transforms into the marts schema, and reindexes OpenSearch automatically:

```bash
# Stage 1 — Regions
docker exec opensearch2-app php artisan sync:run regions

# Stage 2 — Reference / lookup tables
docker exec opensearch2-app php artisan sync:run cities
docker exec opensearch2-app php artisan sync:run service-providers
docker exec opensearch2-app php artisan sync:run users
docker exec opensearch2-app php artisan sync:run projects-details
docker exec opensearch2-app php artisan sync:run asset-statuses
docker exec opensearch2-app php artisan sync:run contract-types

# Stage 3 — User–project links
docker exec opensearch2-app php artisan sync:run user-projects

# Stage 4 — Properties
docker exec opensearch2-app php artisan sync:run properties

# Stage 5 — Property children & asset metadata
docker exec opensearch2-app php artisan sync:run property-buildings
docker exec opensearch2-app php artisan sync:run asset-categories
docker exec opensearch2-app php artisan sync:run asset-names
docker exec opensearch2-app php artisan sync:run priorities

# Stage 6 — Transactional data (heaviest)
docker exec opensearch2-app php artisan sync:run work-orders
docker exec opensearch2-app php artisan sync:run assets
docker exec opensearch2-app php artisan sync:run commercial-contracts
docker exec opensearch2-app php artisan sync:run contracts

# Stage 7 — Contract children
docker exec opensearch2-app php artisan sync:run payment-details
docker exec opensearch2-app php artisan sync:run contract-months
docker exec opensearch2-app php artisan sync:run packages
```

---

## 4. OpenSearch Reindex (standalone)

Only needed if you seeded fake data or if the OpenSearch index gets out of sync.
`sync:run` and `sync:cycle` handle reindexing automatically — skip this if you used them.

```bash
docker exec opensearch2-app php artisan os:reindex work_orders
docker exec opensearch2-app php artisan os:reindex properties
docker exec opensearch2-app php artisan os:reindex assets
docker exec opensearch2-app php artisan os:reindex users
docker exec opensearch2-app php artisan os:reindex commercial_contracts
docker exec opensearch2-app php artisan os:reindex installments
docker exec opensearch2-app php artisan os:reindex contracts
docker exec opensearch2-app php artisan os:reindex projects
```

---

## 5. Queue Worker

The queue connection is `database`. Start the worker so background jobs are processed:

```bash
docker exec opensearch2-app php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

To run it in the background (detached):

```bash
docker exec -d opensearch2-app php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Check for failed jobs:

```bash
docker exec opensearch2-app php artisan queue:failed
```

Retry failed jobs:

```bash
docker exec opensearch2-app php artisan queue:retry all
```

---

## 6. Scheduler

The scheduler is configured to run `sync:cycle` automatically every 30 minutes. Start it with:

```bash
docker exec opensearch2-app php artisan schedule:work
```

To run it in the background (detached):

```bash
docker exec -d opensearch2-app php artisan schedule:work
```

Alternatively, add a system cron entry that calls `schedule:run` every minute:

```
* * * * * docker exec opensearch2-app php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Useful Maintenance Commands

```bash
# Clear application cache
docker exec opensearch2-app php artisan cache:clear

# View sync cycle log
docker exec opensearch2-app tail -f storage/logs/sync-cycle.log

# Wipe ALL DWH data (Postgres + OpenSearch) — use only to start fresh
docker exec opensearch2-app php artisan dwh:wipe --force

# View all containers status
docker-compose ps

# View container logs
docker-compose logs -f

# Stop containers
docker-compose down

# Stop and remove volumes (wipes database data)
docker-compose down -v
```

---

## Quick Reference

| Goal | Command |
|------|---------|
| Start everything | `docker-compose up -d --build` |
| Full data sync | `docker exec opensearch2-app php artisan sync:cycle` |
| Reindex OpenSearch only | `php artisan os:reindex <entity>` |
| Start queue worker | `php artisan queue:work` |
| Start scheduler | `php artisan schedule:work` |
| Wipe and start fresh | `php artisan dwh:wipe --force` |

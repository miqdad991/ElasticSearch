# DWH Dashboard Spec — Work Orders

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-minute push from source system calling DWH APIs
**Delete policy:** hard delete (rows disappear from DWH when deleted from source)

---

## 1. Dashboard summary

Covered UIs:
- `/work-orders` — global work-orders report across all projects
- `/project-dashboard/workorders` — same, filtered to the currently selected project

Supports operational decisions: throughput, SLA, cost per category/property/priority, service-provider workload, maintenance-request funnel.

---

## 2. UI inventory (cards, charts, filters, table)

### Cards (both dashboards)

| Card | Formula (source-system terms) |
|---|---|
| Total Work Orders | `COUNT(*)` |
| Preventive | `COUNT(*) WHERE work_order_type='preventive'` |
| Reactive | `COUNT(*) WHERE work_order_type='reactive'` |
| Hard Service | `COUNT(*) WHERE service_type='hard'` |
| Soft Service | `COUNT(*) WHERE service_type='soft'` |
| Maintenance Requests | `COUNT(DISTINCT maintanance_request_id)` |
| Service Providers | `COUNT(DISTINCT service_provider_id WHERE service_provider_id > 0)` |
| Total Costs | `SUM(cost)` |
| Finished | `COUNT(*) WHERE workorder_journey='finished'` (global dashboard) |
| Open / In Progress | `COUNT(*) WHERE workorder_journey IN ('submitted','job_execution','job_evaluation','job_approval')` (global dashboard) |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Monthly trend | `YYYY-MM` of `created_at` | `COUNT(*)` |
| By service type | `service_type` | `COUNT(*)` |
| By WO type | `work_order_type` | `COUNT(*)` |
| By journey stage | `workorder_journey` | `COUNT(*)` |
| By status | `status` (int 1–8) | `COUNT(*)` |
| By asset category | top 10 `asset_categories.asset_category` | `COUNT(*)` |
| By priority | `priorities.priority_level` | `COUNT(*)` |
| By property/building | top 10 `property_buildings.building_name` | `COUNT(*)` |

### Filters

`service_type`, `work_order_type`, `contract_type`, `workorder_journey`, `status`, `priority_id`, `property_id`, `asset_category_id`, `asset_name_id`, `year`, `created_at` date range, free-text search on `work_order_id` + `asset_categories.asset_category`, plus `project_user_id ∈ (project's users)` on the per-project view.

### Table columns

`work_order_id`, `created_at`, `service_type`, `work_order_type`, `contract_type`, category, priority, `workorder_journey`, status, `start_date`, `end_date`, `target_date`.

---

## 3. Source map (MySQL)

Primary: `work_orders`. Lookups joined on the fly.

| Target column | Source | Notes |
|---|---|---|
| `wo_id` | `work_orders.id` (PK) | |
| `wo_number` | `work_orders.work_order_id` | `VARCHAR(100)`, business key |
| `project_user_id` | `work_orders.project_user_id` | Links to project via `user_projects.user_id = project_user_id` |
| `service_provider_id` | `work_orders.service_provider_id` | `0` when unassigned |
| `property_id` | `work_orders.property_id` | FK → `property_buildings.id` |
| `unit_id` | `work_orders.unit_id` | |
| `asset_category_id` | `work_orders.asset_category_id` | FK → `asset_categories.id` |
| `asset_name_id` | `work_orders.asset_name_id` | FK → `asset_names.id` |
| `priority_id` | `work_orders.priority_id` | FK → `priorities.id` |
| `contract_id` | `work_orders.contract_id` | FK → `contracts.id` |
| `contract_type` | `work_orders.contract_type` | enum('regular','warranty') |
| `maintenance_request_id` | `work_orders.maintanance_request_id` | **note typo in source** |
| `work_order_type` | `work_orders.work_order_type` | enum('reactive','preventive') |
| `service_type` | `work_orders.service_type` | enum('soft','hard') |
| `workorder_journey` | `work_orders.workorder_journey` | 5-value enum |
| `status_code` | `work_orders.status` | int 1–8, see §5.4 |
| `cost` | `work_orders.cost` | `DECIMAL(18,2)` |
| `score` | `work_orders.score` | `DOUBLE` |
| `pass_fail` | `work_orders.pass_fail` | enum('pass','fail','pending') |
| `sla_response_time` | `work_orders.sla_response_time` | |
| `response_time_type` | `work_orders.response_time_type` | enum('days','hours','minutes') |
| `sla_service_window` | `work_orders.sla_service_window` | |
| `service_window_type` | `work_orders.service_window_type` | enum('days','hours','minutes') |
| `start_date` | `work_orders.start_date` | |
| `end_date` | `work_orders.end_date` | |
| `target_date` | `work_orders.target_date` | |
| `job_started_at` | `work_orders.job_started_at` | |
| `job_submitted_at` | `work_orders.job_submitted_at` | |
| `job_completion_date` | `work_orders.job_completion_date` | |
| `created_at` | `work_orders.created_at` | cursor column |
| `modified_at` | `work_orders.modified_at` | cursor column |
| (filter out) | `work_orders.is_deleted='yes'` | do not load rejected rows |

---

## 4. Target Postgres schema

Three schemas: `raw` (1:1 landing), `marts` (facts/dims), `reports` (materialized views).

### 4.1 Dimensions (build once, reuse across dashboards)

```sql
CREATE SCHEMA IF NOT EXISTS raw;
CREATE SCHEMA IF NOT EXISTS marts;
CREATE SCHEMA IF NOT EXISTS reports;

-- Calendar dim, pre-seeded 2015-01-01 .. 2035-12-31
CREATE TABLE marts.dim_date (
    date_key         DATE PRIMARY KEY,
    year             SMALLINT NOT NULL,
    quarter          SMALLINT NOT NULL,
    month            SMALLINT NOT NULL,
    month_name       TEXT    NOT NULL,
    week             SMALLINT NOT NULL,
    day_of_month     SMALLINT NOT NULL,
    day_of_week      SMALLINT NOT NULL,
    is_weekend       BOOLEAN NOT NULL,
    iso_year_month   CHAR(7) GENERATED ALWAYS AS (to_char(date_key, 'YYYY-MM')) STORED
);
CREATE INDEX ix_dim_date_ym ON marts.dim_date(iso_year_month);

CREATE TABLE marts.dim_service_provider (
    sp_id             BIGINT PRIMARY KEY,              -- = service_providers.id
    name              TEXT NOT NULL,
    status            SMALLINT,
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    source_updated_at TIMESTAMPTZ,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_asset_category (
    asset_category_id BIGINT PRIMARY KEY,
    asset_category    TEXT NOT NULL,
    service_type      TEXT,
    status            SMALLINT,
    source_updated_at TIMESTAMPTZ,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_asset_name (
    asset_name_id BIGINT PRIMARY KEY,
    asset_name    TEXT NOT NULL,
    loaded_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_priority (
    priority_id           BIGINT PRIMARY KEY,
    priority_level        TEXT NOT NULL,
    service_window        INT,
    service_window_type   TEXT,
    response_time         NUMERIC,
    response_time_type    TEXT,
    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_property_building (
    property_id    BIGINT PRIMARY KEY,      -- = property_buildings.id
    building_name  TEXT,
    property_owner_id BIGINT,
    parent_property_id BIGINT,              -- properties.id
    loaded_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_user (
    user_id           BIGINT PRIMARY KEY,
    name              TEXT,
    email             TEXT,
    phone             TEXT,
    user_type         TEXT,
    project_user_id   BIGINT,              -- self-FK, project admin user
    status            SMALLINT,
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_project (
    project_id        BIGINT PRIMARY KEY,          -- projects_details.id
    project_name      TEXT NOT NULL,
    industry_type     TEXT,
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Many-to-many: users can belong to multiple projects
CREATE TABLE marts.bridge_user_project (
    user_id    BIGINT NOT NULL REFERENCES marts.dim_user(user_id),
    project_id BIGINT NOT NULL REFERENCES marts.dim_project(project_id),
    PRIMARY KEY (user_id, project_id)
);
CREATE INDEX ix_bup_project ON marts.bridge_user_project(project_id);
```

### 4.2 Fact table

```sql
CREATE TYPE marts.wo_type_enum    AS ENUM ('reactive','preventive');
CREATE TYPE marts.wo_service_enum AS ENUM ('soft','hard');
CREATE TYPE marts.wo_contract_enum AS ENUM ('regular','warranty');
CREATE TYPE marts.wo_journey_enum AS ENUM ('submitted','job_execution','job_evaluation','job_approval','finished');
CREATE TYPE marts.wo_pass_fail_enum AS ENUM ('pass','fail','pending');
CREATE TYPE marts.wo_time_unit_enum AS ENUM ('days','hours','minutes');

CREATE TABLE marts.fact_work_order (
    wo_id                 BIGINT PRIMARY KEY,              -- = work_orders.id
    wo_number             VARCHAR(100) NOT NULL UNIQUE,
    project_user_id       BIGINT REFERENCES marts.dim_user(user_id),
    service_provider_id   BIGINT REFERENCES marts.dim_service_provider(sp_id),
    property_id           BIGINT REFERENCES marts.dim_property_building(property_id),
    unit_id               INT,
    asset_category_id     BIGINT REFERENCES marts.dim_asset_category(asset_category_id),
    asset_name_id         BIGINT REFERENCES marts.dim_asset_name(asset_name_id),
    priority_id           BIGINT REFERENCES marts.dim_priority(priority_id),
    contract_id           BIGINT,
    contract_type         marts.wo_contract_enum,
    maintenance_request_id INT,
    work_order_type       marts.wo_type_enum,
    service_type          marts.wo_service_enum,
    workorder_journey     marts.wo_journey_enum,
    status_code           SMALLINT,                        -- 1..8
    status_label          TEXT,                            -- derived, see §5.4
    cost                  NUMERIC(18,2) NOT NULL DEFAULT 0,
    score                 DOUBLE PRECISION NOT NULL DEFAULT 0,
    pass_fail             marts.wo_pass_fail_enum,
    sla_response_time     NUMERIC,
    response_time_type    marts.wo_time_unit_enum,
    sla_service_window    INT,
    service_window_type   marts.wo_time_unit_enum,

    -- dates (FKs to dim_date for analytics)
    created_date_key      DATE GENERATED ALWAYS AS ((created_at AT TIME ZONE 'UTC')::date) STORED,
    start_date            DATE,
    end_date              DATE,
    target_at             TIMESTAMPTZ,
    job_started_at        TIMESTAMPTZ,
    job_submitted_at      TIMESTAMPTZ,
    job_completion_at     TIMESTAMPTZ,

    created_at            TIMESTAMPTZ NOT NULL,
    source_updated_at     TIMESTAMPTZ NOT NULL,

    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (created_date_key) REFERENCES marts.dim_date(date_key)
) PARTITION BY RANGE (created_at);

-- Initial partitions; cron job adds future ones
CREATE TABLE marts.fact_work_order_y2024 PARTITION OF marts.fact_work_order
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
CREATE TABLE marts.fact_work_order_y2025 PARTITION OF marts.fact_work_order
    FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
CREATE TABLE marts.fact_work_order_y2026 PARTITION OF marts.fact_work_order
    FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
CREATE TABLE marts.fact_work_order_y2027 PARTITION OF marts.fact_work_order
    FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');

CREATE INDEX ix_fwo_created_date   ON marts.fact_work_order(created_date_key);
CREATE INDEX ix_fwo_sp             ON marts.fact_work_order(service_provider_id);
CREATE INDEX ix_fwo_category       ON marts.fact_work_order(asset_category_id);
CREATE INDEX ix_fwo_property       ON marts.fact_work_order(property_id);
CREATE INDEX ix_fwo_priority       ON marts.fact_work_order(priority_id);
CREATE INDEX ix_fwo_project_user   ON marts.fact_work_order(project_user_id);
CREATE INDEX ix_fwo_status         ON marts.fact_work_order(status_code);
CREATE INDEX ix_fwo_journey        ON marts.fact_work_order(workorder_journey);
CREATE INDEX ix_fwo_mr             ON marts.fact_work_order(maintenance_request_id);
```

### 4.3 Raw landing tables (1:1 with API payload)

```sql
CREATE TABLE raw.work_orders (
    id                   BIGINT PRIMARY KEY,
    payload              JSONB NOT NULL,
    ingested_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE raw.service_providers (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.asset_categories (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.asset_names (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.priorities (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.property_buildings (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.users (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.projects_details (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.user_projects (
    user_id    BIGINT NOT NULL,
    project_id BIGINT NOT NULL,
    ingested_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, project_id)
);
```

---

## 5. ETL transforms

### 5.1 Filter
- Drop rows where `work_orders.is_deleted = 'yes'`. Hard delete: source is expected to not send them, and any row already in the fact that is missing from the latest batch's id list for touched partitions is deleted in the cleanup step (§6.3).

### 5.2 Enum / type coercion
- ENUMs mapped 1:1 to Postgres ENUM types (see DDL). Invalid values → NULL, logged.
- `is_deleted` `'yes'`/`'no'` → boolean (for dims).
- Empty strings in date/time columns → NULL.

### 5.3 Dim lookups
- `project_user_id` in source = the project's admin user. In the DWH, stored as-is on `fact_work_order`. To get "all projects this WO belongs to", join through `bridge_user_project` (since a user can belong to multiple projects in `user_projects`).

### 5.4 Status label derivation
```
1 → Open
2 → In Progress
3 → On Hold
4 → Closed
5 → Deleted
6 → Re-open
7 → Warranty
8 → Scheduled
```
Populate `status_label` in the transform. Unknown values → `'Status ' || status_code`.

### 5.5 Derived columns
- `created_date_key` — generated column (see DDL), used by `dim_date` join.
- No KPI math in this fact — kept raw.

### 5.6 Deduplication
- Upsert on `wo_id` (PK). Source system guarantees uniqueness.

---

## 6. Incremental load strategy

### 6.1 Cursor
Source calls DWH API every 30 minutes with all rows where:
```
modified_at > last_successful_cursor - 10 minutes   (10-min overlap for safety)
```
DWH stores `dwh.sync_state(table_name, last_cursor, last_run_at)` and returns the cursor for the next call.

### 6.2 Upsert rule
```sql
INSERT INTO marts.fact_work_order (...) VALUES (...)
ON CONFLICT (wo_id) DO UPDATE SET
    cost = EXCLUDED.cost,
    status_code = EXCLUDED.status_code,
    status_label = EXCLUDED.status_label,
    workorder_journey = EXCLUDED.workorder_journey,
    ... (all non-PK columns)
    source_updated_at = EXCLUDED.source_updated_at,
    loaded_at = now();
```

### 6.3 Hard delete reconciliation
Source API includes a `deleted_ids` array in every call — IDs that were deleted since the previous cursor. DWH runs:
```sql
DELETE FROM marts.fact_work_order WHERE wo_id = ANY($1);
```

### 6.4 Partition maintenance
Monthly cron creates the next year's partition if not present.

---

## 7. Materialized view for the dashboard

Refreshed every 30 min (after each load) with `REFRESH MATERIALIZED VIEW CONCURRENTLY`.

```sql
CREATE MATERIALIZED VIEW reports.mv_work_order_kpis AS
SELECT
    d.iso_year_month                                     AS year_month,
    f.project_user_id,
    f.service_provider_id,
    f.asset_category_id,
    f.property_id,
    f.priority_id,
    f.work_order_type,
    f.service_type,
    f.workorder_journey,
    f.status_code,
    COUNT(*)                                             AS wo_count,
    COUNT(DISTINCT f.maintenance_request_id) FILTER (WHERE f.maintenance_request_id IS NOT NULL) AS maintenance_requests,
    COUNT(DISTINCT f.service_provider_id)    FILTER (WHERE f.service_provider_id > 0)             AS service_providers,
    COALESCE(SUM(f.cost), 0)                             AS total_cost
FROM marts.fact_work_order f
JOIN marts.dim_date d ON d.date_key = f.created_date_key
GROUP BY CUBE(
    d.iso_year_month,
    f.project_user_id, f.service_provider_id, f.asset_category_id,
    f.property_id, f.priority_id,
    f.work_order_type, f.service_type, f.workorder_journey, f.status_code
);

CREATE UNIQUE INDEX ix_mv_wo_kpis ON reports.mv_work_order_kpis
    (year_month, project_user_id, service_provider_id, asset_category_id,
     property_id, priority_id, work_order_type, service_type, workorder_journey, status_code);
```

Per-project filtering uses `bridge_user_project` to resolve `project_id → project_user_id ∈ (list)`.

---

## 8. API contract (source → DWH)

### 8.1 Common envelope

```http
POST /api/dwh/ingest/work-orders
Authorization: Bearer <service-token>
X-Idempotency-Key: <uuid-per-batch>
Content-Type: application/json
```

Request:
```json
{
  "cursor_from": "2026-04-13T09:00:00Z",
  "cursor_to":   "2026-04-13T09:30:00Z",
  "rows": [ /* see 8.2 */ ],
  "deleted_ids": [1234, 5678]
}
```

Response:
```json
{ "accepted": 482, "upserted": 481, "deleted": 2, "invalid": 1, "next_cursor": "2026-04-13T09:30:00Z" }
```

Server returns **200** only if the whole batch is durably persisted. Any partial failure → **409** and no state changes; client retries with same idempotency key.

### 8.2 Work order row shape

```json
{
  "id": 12345,
  "work_order_id": "WO-000123",
  "project_user_id": 45,
  "service_provider_id": 12,
  "property_id": 101,
  "unit_id": 5,
  "asset_category_id": 7,
  "asset_name_id": 22,
  "priority_id": 3,
  "contract_id": 18,
  "contract_type": "regular",
  "maintenance_request_id": 987,
  "work_order_type": "preventive",
  "service_type": "hard",
  "workorder_journey": "finished",
  "status": 4,
  "cost": "150.50",
  "score": 0,
  "pass_fail": "pass",
  "sla_response_time": 4,
  "response_time_type": "hours",
  "sla_service_window": 48,
  "service_window_type": "hours",
  "start_date": "2026-03-01",
  "end_date": "2026-03-02",
  "target_date": "2026-03-02T17:00:00Z",
  "job_started_at": "2026-03-01T09:00:00Z",
  "job_submitted_at": "2026-03-01T15:00:00Z",
  "job_completion_date": "2026-03-01T15:10:00Z",
  "created_at": "2026-02-28T12:00:00Z",
  "modified_at": "2026-03-01T15:10:00Z",
  "is_deleted": "no"
}
```

Rows with `is_deleted = "yes"` are skipped by the DWH loader (keep `deleted_ids` as the authoritative channel).

### 8.3 Companion endpoints (same envelope pattern)

- `POST /api/dwh/ingest/service-providers`
- `POST /api/dwh/ingest/asset-categories`
- `POST /api/dwh/ingest/asset-names`
- `POST /api/dwh/ingest/priorities`
- `POST /api/dwh/ingest/property-buildings`
- `POST /api/dwh/ingest/users`
- `POST /api/dwh/ingest/projects`
- `POST /api/dwh/ingest/user-projects` (bridge)

Run these **before** the work-orders endpoint in each cycle to avoid orphan FKs (or use deferred constraints — see §9.4).

### 8.4 Ordering & atomicity

Within a single 30-minute cycle the client should call dim endpoints first, then `work-orders`. If a FK lookup fails during WO load, the row is parked in `raw.work_orders_dlq` for the next cycle.

---

## 9. Validation checks (dbt tests)

```yaml
# models/marts/fact_work_order.yml
models:
  - name: fact_work_order
    tests:
      - dbt_utils.expression_is_true:
          expression: "cost >= 0"
      - dbt_utils.expression_is_true:
          expression: "status_code between 1 and 8"
    columns:
      - name: wo_id
        tests: [not_null, unique]
      - name: wo_number
        tests: [not_null, unique]
      - name: created_at
        tests: [not_null]
      - name: service_provider_id
        tests:
          - relationships: { to: ref('dim_service_provider'), field: sp_id, severity: warn }
      - name: asset_category_id
        tests:
          - relationships: { to: ref('dim_asset_category'), field: asset_category_id, severity: warn }
```

Operational checks (on a `checks` dashboard):
- Row count diff MySQL vs DWH daily → alert if `>0.5%`
- Cursor lag > 60 minutes → PagerDuty
- `mv_work_order_kpis` refresh age > 45 minutes → alert
- Rows in `raw.work_orders_dlq` > 100 → alert

### 9.4 FK strategy
All FKs on `fact_work_order` are **DEFERRABLE INITIALLY DEFERRED** (not shown above for brevity — add `DEFERRABLE INITIALLY DEFERRED` to each). Lets you load facts before dims in a transaction.

---

## 10. Open questions

1. Does the source system have a reliable `modified_at` on every mutation? If a row is updated but the column doesn't bump, we'll miss changes. **Mitigation:** 10-minute overlap window + daily full-refresh reconciliation job.
2. `supervisor_id` is a `longtext` field containing CSV or JSON — format varies. Not used in this dashboard, parked for the contracts doc.
3. `work_orders.project_user_id` can be `0` for some rows — keep or filter? Current dashboard does not filter; DWH will keep (FK nullable).
4. Do we want `work_order_status_changes` as a separate fact for lifecycle timing (submitted→finished duration)? Not in scope here — candidate for dashboard #2.
5. Source API authentication mechanism (bearer JWT vs mTLS) — ops decision.
6. Timezone of `created_at` in source — assumed UTC. If source is in local time (Asia/Riyadh), add `AT TIME ZONE 'Asia/Riyadh'` in the transform before storing as `TIMESTAMPTZ`.

---

## File location

Saved as `docs/dwh/01-work-orders.md`. Next dashboards: `02-properties.md`, `03-assets.md`, etc.

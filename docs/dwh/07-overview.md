# DWH Dashboard Spec — Platform Overview

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

> The landing-page rollup. Most of its numbers come from materialized views already built in docs 01–06. This doc only adds **two new source tables** (`packages`, `service_providers_project_mapping`) and the composite view that powers the page.

---

## 1. Dashboard summary

Covered UI:
- `/overview` — platform-wide executive summary.

Decisions supported: portfolio health at a glance, subscription revenue, admin user count, service-provider coverage per project, payment due/overdue per project.

---

## 2. UI inventory

### Cards

| Card | Formula | Comes from |
|---|---|---|
| Total Projects | `COUNT(projects_details.*) WHERE is_deleted=0` | `dim_project` |
| Active Projects | `COUNT(*) WHERE is_deleted=0` | `dim_project` |
| Inactive Projects | `COUNT(*) WHERE is_deleted=1` | `dim_project` |
| Total Properties | `COUNT(properties.*) WHERE is_deleted='no'` | `dim_property` |
| Total Service Providers | `COUNT(service_providers.*) WHERE is_deleted='no'` | `dim_service_provider` |
| Total Admins | `COUNT(users.*) WHERE user_type='admin'` | `dim_user` |
| Total Subscriptions | `COUNT(packages.*) WHERE deleted_at IS NULL` | `dim_subscription_package` (new) |
| Active Subscriptions | `COUNT(*) WHERE status='active'` | `dim_subscription_package` |
| Inactive Subscriptions | `COUNT(*) WHERE status != 'active' OR status IS NULL` | `dim_subscription_package` |
| Subscription Value | `SUM(CASE WHEN discount>0 THEN price - price*discount/100 ELSE price END)` | `dim_subscription_package` |

### Per-project rollups (displayed in the projects list)

| Column | Source |
|---|---|
| Managed properties | `mv_property_kpis` grouped by `owner_user_id` → projects via `bridge_user_project` |
| Service providers | `bridge_sp_project` (new) |
| Contract value | `dim_contract.contract_value` summed per project via `owner_user_id` (execution) |
| Payment due | `fact_commercial_contract.payment_due` summed by `project_id` (lease) |
| Payment overdue | `fact_commercial_contract.payment_overdue` summed by `project_id` (lease) |

### Subscriptions list

`name`, `pricing_model`, `price`, `discount`, `status`, `most_popular`, `created_at`.

### Projects list

`id`, `project_name`, `industry_type`, `is_deleted`, `contract_status`, `contract_start_date`, `contract_end_date`, module flags (`use_erp_module`, `use_crm_module`, `use_tenant_module`, `use_beneficiary_module`, CRM flags), `owner_name`, `created_at`.

---

## 3. Source map — new tables only

### 3.1 `packages` (subscription catalog)

| Target | Source | Notes |
|---|---|---|
| `package_id` | `packages.id` | PK |
| `name` | `.name` | |
| `pricing_model` | `.pricing_model` | e.g. `monthly`, `yearly`, `one_time` |
| `price` | `.price` | NUMERIC |
| `discount` | `.discount` | NUMERIC — percentage |
| `status` | `.status` | text — `active`/`inactive`/NULL |
| `most_popular` | `.most_popular` | tinyint → boolean |
| `created_at` | `.created_at` | cursor |
| `modified_at` | `.modified_at` | cursor |
| (filter) | `.deleted_at IS NOT NULL` | |

### 3.2 `service_providers_project_mapping`

| Target | Source |
|---|---|
| `service_provider_id` | `service_provider_id` |
| `project_id` | `project_id` |

### 3.3 `projects_details` — full column set (doc #1 declared the stub)

| Target | Source |
|---|---|
| `project_id` | `projects_details.id` |
| `user_id` | `.user_id` | owner user |
| `project_name` | `.project_name` |
| `industry_type` | `.industry_type` |
| `contract_status` | `.contract_status` |
| `contract_start_date` | `.contract_start_date` |
| `contract_end_date` | `.contract_end_date` |
| `use_erp_module` | `.use_erp_module` | tinyint → boolean |
| `use_crm_module` | `.use_crm_module` | tinyint → boolean |
| `use_tenant_module` | `.use_tenant_module` | tinyint → boolean |
| `use_beneficiary_module` | `.use_beneficiary_module` | tinyint → boolean |
| `enable_crm_projects` | `.enable_crm_projects` | tinyint → boolean |
| `enable_crm_sales` | `.enable_crm_sales` | tinyint → boolean |
| `enable_crm_finance` | `.enable_crm_finance` | tinyint → boolean |
| `enable_crm_rfx` | `.enable_crm_rfx` | tinyint → boolean |
| `enable_crm_documents` | `.enable_crm_documents` | tinyint → boolean |
| `is_deleted` | `.is_deleted` | tinyint → boolean (source uses 0/1 here, not yes/no) |
| `created_at` | `.created_at` | cursor |
| `modified_at` | `.modified_at` | cursor |

---

## 4. Target Postgres schema

### 4.1 Extended `dim_project`

```sql
DROP TABLE IF EXISTS marts.dim_project CASCADE;

CREATE TABLE marts.dim_project (
    project_id              INT PRIMARY KEY,
    owner_user_id           BIGINT REFERENCES marts.dim_user(user_id),
    project_name            TEXT NOT NULL,
    industry_type           TEXT,
    contract_status         TEXT,
    contract_start_date     DATE,
    contract_end_date       DATE,

    use_erp_module          BOOLEAN NOT NULL DEFAULT FALSE,
    use_crm_module          BOOLEAN NOT NULL DEFAULT FALSE,
    use_tenant_module       BOOLEAN NOT NULL DEFAULT FALSE,
    use_beneficiary_module  BOOLEAN NOT NULL DEFAULT FALSE,
    enable_crm_projects     BOOLEAN NOT NULL DEFAULT FALSE,
    enable_crm_sales        BOOLEAN NOT NULL DEFAULT FALSE,
    enable_crm_finance      BOOLEAN NOT NULL DEFAULT FALSE,
    enable_crm_rfx          BOOLEAN NOT NULL DEFAULT FALSE,
    enable_crm_documents    BOOLEAN NOT NULL DEFAULT FALSE,

    is_active               BOOLEAN NOT NULL DEFAULT TRUE,     -- derived: NOT is_deleted
    is_deleted              BOOLEAN NOT NULL DEFAULT FALSE,

    created_at              TIMESTAMPTZ NOT NULL,
    source_updated_at       TIMESTAMPTZ,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_dim_project_owner ON marts.dim_project(owner_user_id);
CREATE INDEX ix_dim_project_active ON marts.dim_project(is_active);
```

### 4.2 `dim_subscription_package`

```sql
CREATE TABLE marts.dim_subscription_package (
    package_id         BIGINT PRIMARY KEY,
    name               TEXT NOT NULL,
    pricing_model      TEXT,
    price              NUMERIC(18,2) NOT NULL DEFAULT 0,
    discount           NUMERIC(5,2)  NOT NULL DEFAULT 0,
    effective_price    NUMERIC(18,2) GENERATED ALWAYS AS
                           (CASE WHEN discount > 0
                                 THEN price - (price * discount / 100)
                                 ELSE price END) STORED,
    status             TEXT,
    is_active          BOOLEAN GENERATED ALWAYS AS (status = 'active') STORED,
    most_popular       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at         TIMESTAMPTZ NOT NULL,
    source_updated_at  TIMESTAMPTZ,
    loaded_at          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_dim_sub_active ON marts.dim_subscription_package(is_active);
```

### 4.3 `bridge_sp_project`

```sql
CREATE TABLE marts.bridge_sp_project (
    service_provider_id BIGINT NOT NULL REFERENCES marts.dim_service_provider(sp_id) ON DELETE CASCADE,
    project_id          INT    NOT NULL REFERENCES marts.dim_project(project_id)       ON DELETE CASCADE,
    PRIMARY KEY (service_provider_id, project_id)
);
CREATE INDEX ix_bsp_project ON marts.bridge_sp_project(project_id);
```

### 4.4 Raw landing

```sql
CREATE TABLE raw.packages (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now()
);
CREATE TABLE raw.service_providers_project_mapping (
    service_provider_id BIGINT NOT NULL,
    project_id          INT    NOT NULL,
    ingested_at         TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (service_provider_id, project_id)
);
-- raw.projects_details already declared in doc #1 — extend payload shape.
```

---

## 5. ETL transforms

1. **Filter** — drop packages with `deleted_at IS NOT NULL`; drop projects with `is_deleted=1`.
2. **Boolean normalization** — `projects_details.is_deleted` uses `0/1` (different from most source tables that use `yes/no`). ETL must map `0` → FALSE, `1` → TRUE. Module flags all tinyint → boolean.
3. **Effective price** handled by generated column; nothing to compute at load time.
4. **Status text** — lowercased at load for consistent filtering.
5. **Bridges** — `bridge_sp_project` replaced per-SP on each cycle (delete rows for the SP then re-insert).
6. **Upserts** — PK on `projects_details.id`, `packages.id`, composite PK on mapping.

---

## 6. Incremental load

### 6.1 Cursors
```
packages.modified_at                        > :c_pkg   - 10 min
projects_details.modified_at                > :c_proj  - 10 min
service_providers_project_mapping           → full refresh every cycle  (small static table)
```
SP↔project mapping is small (<10k rows) and lacks a cursor — do a full replace with a diff algorithm:
```sql
BEGIN;
CREATE TEMP TABLE _incoming (service_provider_id BIGINT, project_id INT);
-- COPY incoming rows into _incoming
DELETE FROM marts.bridge_sp_project b
  WHERE NOT EXISTS (SELECT 1 FROM _incoming i
                     WHERE i.service_provider_id = b.service_provider_id
                       AND i.project_id          = b.project_id);
INSERT INTO marts.bridge_sp_project
SELECT * FROM _incoming
ON CONFLICT DO NOTHING;
COMMIT;
```

### 6.2 Hard delete
`deleted_ids[]` per endpoint. For `dim_project` delete, confirm there are no dependent fact rows first — run a pre-check and, if any, cascade manually (facts reference `owner_user_id` or `project_id` with `ON DELETE SET NULL`).

---

## 7. Materialized views

### 7.1 Platform totals (single-row view that feeds the header cards)

```sql
CREATE MATERIALIZED VIEW reports.mv_overview_totals AS
SELECT
    (SELECT COUNT(*) FROM marts.dim_project WHERE NOT is_deleted)              AS active_projects,
    (SELECT COUNT(*) FROM marts.dim_project WHERE is_deleted)                  AS inactive_projects,
    (SELECT COUNT(*) FROM marts.dim_project)                                   AS total_projects,

    (SELECT COUNT(*) FROM marts.dim_property WHERE NOT is_deleted)             AS total_properties,
    (SELECT COUNT(*) FROM marts.dim_service_provider WHERE NOT is_deleted)     AS total_service_providers,
    (SELECT COUNT(*) FROM marts.dim_user
        WHERE user_type = 'admin' AND NOT is_deleted)                          AS total_admins,

    (SELECT COUNT(*) FROM marts.dim_subscription_package)                      AS total_subscriptions,
    (SELECT COUNT(*) FROM marts.dim_subscription_package WHERE is_active)      AS active_subscriptions,
    (SELECT COUNT(*) FROM marts.dim_subscription_package WHERE NOT is_active)  AS inactive_subscriptions,
    (SELECT COALESCE(SUM(effective_price),0) FROM marts.dim_subscription_package) AS subscription_value,

    now() AS computed_at;

CREATE UNIQUE INDEX ix_mv_overview ON reports.mv_overview_totals ((computed_at IS NOT NULL));
```
(The dummy unique index lets `REFRESH ... CONCURRENTLY` run on a single-row mv.)

### 7.2 Per-project rollup (the projects list)

```sql
CREATE MATERIALIZED VIEW reports.mv_project_rollup AS
WITH project_users AS (
    SELECT up.project_id, up.user_id
    FROM marts.bridge_user_project up
),
prop_counts AS (
    SELECT pu.project_id, COUNT(DISTINCT p.property_id) AS property_count
    FROM project_users pu
    JOIN marts.dim_property p ON p.owner_user_id = pu.user_id AND NOT p.is_deleted
    GROUP BY pu.project_id
),
sp_counts AS (
    SELECT project_id, COUNT(DISTINCT service_provider_id) AS sp_count
    FROM marts.bridge_sp_project
    GROUP BY project_id
),
contract_value AS (
    SELECT pu.project_id, COALESCE(SUM(dc.contract_value), 0) AS total_contract_value
    FROM project_users pu
    JOIN marts.dim_contract dc ON dc.owner_user_id = pu.user_id
    WHERE dc.is_current AND NOT dc.is_deleted
    GROUP BY pu.project_id
),
lease_money AS (
    SELECT project_id,
           COALESCE(SUM(payment_due),0)     AS payment_due,
           COALESCE(SUM(payment_overdue),0) AS payment_overdue,
           COALESCE(SUM(amount),0)          AS lease_value
    FROM marts.fact_commercial_contract
    WHERE NOT is_deleted
    GROUP BY project_id
)
SELECT
    p.project_id,
    p.project_name,
    p.industry_type,
    p.is_deleted,
    p.contract_status,
    p.contract_start_date,
    p.contract_end_date,
    p.use_erp_module,  p.use_crm_module, p.use_tenant_module, p.use_beneficiary_module,
    p.enable_crm_projects, p.enable_crm_sales, p.enable_crm_finance,
    p.enable_crm_rfx, p.enable_crm_documents,
    p.created_at,
    u.full_name                      AS owner_name,
    COALESCE(pc.property_count, 0)   AS property_count,
    COALESCE(sc.sp_count, 0)         AS sp_count,
    COALESCE(cv.total_contract_value, 0) AS contract_value,
    COALESCE(lm.payment_due, 0)      AS payment_due,
    COALESCE(lm.payment_overdue, 0)  AS payment_overdue,
    COALESCE(lm.lease_value, 0)      AS lease_value
FROM marts.dim_project p
LEFT JOIN marts.dim_user u ON u.user_id = p.owner_user_id
LEFT JOIN prop_counts  pc ON pc.project_id = p.project_id
LEFT JOIN sp_counts    sc ON sc.project_id = p.project_id
LEFT JOIN contract_value cv ON cv.project_id = p.project_id
LEFT JOIN lease_money   lm ON lm.project_id = p.project_id;

CREATE UNIQUE INDEX ix_mv_proj_rollup ON reports.mv_project_rollup(project_id);
CREATE INDEX ix_mv_proj_rollup_name   ON reports.mv_project_rollup(project_name);
```

### 7.3 Subscription list
Trivial — the dashboard queries `marts.dim_subscription_package` directly, ordered by `created_at DESC`, with pagination. No extra view needed.

All mv's refresh `CONCURRENTLY` after each 30-min load. `mv_overview_totals` can be refreshed on a shorter interval (5 min) if the single-row total needs to feel real-time.

---

## 8. API contract

### 8.1 Endpoints

```
POST /api/dwh/ingest/projects
POST /api/dwh/ingest/packages
POST /api/dwh/ingest/service-provider-project-mapping
```

Call order: projects → packages → sp-project-mapping. Runs after `users` + `service-providers` in the cycle.

### 8.2 Project row

```json
{
  "id": 67,
  "user_id": 45,
  "project_name": "Riyadh Portfolio",
  "industry_type": "Commercial Real Estate",
  "contract_status": "active",
  "contract_start_date": "2024-01-01",
  "contract_end_date": "2026-12-31",
  "use_erp_module": 1,
  "use_crm_module": 1,
  "use_tenant_module": 1,
  "use_beneficiary_module": 0,
  "enable_crm_projects": 1,
  "enable_crm_sales": 1,
  "enable_crm_finance": 1,
  "enable_crm_rfx": 0,
  "enable_crm_documents": 1,
  "is_deleted": 0,
  "created_at": "2024-01-05T08:00:00Z",
  "modified_at": "2026-03-10T11:00:00Z"
}
```

### 8.3 Package row

```json
{
  "id": 7,
  "name": "Enterprise Plus",
  "pricing_model": "yearly",
  "price": "12000.00",
  "discount": "10.00",
  "status": "active",
  "most_popular": 1,
  "created_at": "2023-11-01T00:00:00Z",
  "modified_at": "2025-09-01T00:00:00Z",
  "deleted_at": null
}
```

### 8.4 SP-project mapping row

```json
{ "service_provider_id": 12, "project_id": 67 }
```

With the **full-refresh-per-cycle** rule: `rows` contains the complete current state and `deleted_ids` is unused for this endpoint.

---

## 9. Validation checks

```yaml
models:
  - name: dim_project
    columns:
      - name: project_id
        tests: [not_null, unique]
      - name: project_name
        tests: [not_null]
      - name: owner_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }

  - name: dim_subscription_package
    columns:
      - name: package_id
        tests: [not_null, unique]
    tests:
      - dbt_utils.expression_is_true: { expression: "price >= 0" }
      - dbt_utils.expression_is_true: { expression: "discount BETWEEN 0 AND 100" }
      - dbt_utils.expression_is_true: { expression: "effective_price >= 0" }

  - name: bridge_sp_project
    columns:
      - name: service_provider_id
        tests:
          - relationships: { to: ref('dim_service_provider'), field: sp_id }
      - name: project_id
        tests:
          - relationships: { to: ref('dim_project'), field: project_id }
```

Operational alerts:
- `mv_overview_totals.computed_at` age > 45 min → data staleness.
- `mv_project_rollup.property_count = 0` for a project > X days old → likely broken `bridge_user_project` or stale mapping.
- Total active subscriptions drops > 10% between refreshes → data drift.
- Row count diff MySQL `projects_details` vs `dim_project` > 0 → reconciliation alert.

---

## 10. Open questions

1. `projects_details.is_deleted` uses `0/1` (tinyint) whereas most other sources use `yes/no` — confirm ETL normalization and keep a unit test for it.
2. Are deleted projects allowed to still appear in the overview list (read-only)? The dashboard currently shows both. Confirm.
3. `packages.pricing_model` free text — canonicalize to `{monthly,yearly,quarterly,one_time,free}` or keep raw? Assumed raw.
4. Subscription revenue formula uses `price − price × discount/100`. If tax/VAT enters the picture, we need a new column in `dim_subscription_package`.
5. `mv_project_rollup.contract_value` only includes **execution** contracts via `dim_contract`. Should it also sum `fact_commercial_contract.amount`? If yes, combine them, but be explicit about "total value" meaning in the UI.
6. `service_providers_project_mapping` has no timestamps. If a SP↔project link is deleted in source but we miss the full-refresh payload once, the dashboard will over-count. **Mitigation:** full refresh strictly enforced per cycle; alert if the payload count drops sharply.
7. `packages.deleted_at` is NULL in the sample schema but absent from the DESCRIBE — verify whether it exists. If not, use `is_deleted` or similar.
8. Should we carry the `industry_type` as an FK to a `dim_industry` instead of free text? Low priority; current UI shows it as-is.
9. Overview should probably show total contract spend (WO extras + payments) across the platform — out of scope for this doc but easy to bolt on using `mv_contract_wo_extras` from doc #6.

---

## File location

Saved as `docs/dwh/07-overview.md`.

---

## Next steps (suggested)

With all 7 dashboards documented, the natural next deliverables are:

1. **Master migration script** — one SQL file per phase concatenating DDL from docs 01–07 in dependency order.
2. **API OpenAPI spec** — combine all `POST /api/dwh/ingest/*` endpoints from every doc into one `openapi.yaml` for the source team.
3. **`dbt_project.yml`** scaffold with staging/intermediate/marts layers matching this layout.
4. **Orchestration DAG** (Dagster preferred) — one job per 30-min cycle calling the 30+ ingest endpoints in dependency order, then `REFRESH MATERIALIZED VIEW CONCURRENTLY` on the 14 mv's defined across the docs.
5. **Airflow / Dagster test fixtures** — freeze a day of source data, run the full ETL in CI, assert row counts and a golden query matches.

Tell me which to do next.

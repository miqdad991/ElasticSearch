# DWH Dashboard Spec — Execution Contracts & Payment Tracking

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

> Different money domain from doc #5. This covers the **execution-side** `contracts` table — service-provider agreements with monthly schedules, WO extras, KPIs, subcontracts. Not lease/tenant billing.

---

## 1. Dashboard summary

Covered UIs:
- `/contracts-dashboard` — all execution contracts platform-wide.
- `/project-dashboard/contracts` — scoped to the selected project via `contracts.user_id ∈ project_user_ids`.
- `/contracts-dashboard/{id}` — single-contract drill-down.

Decisions supported: service-provider spend, SLA / KPI performance, payment schedule vs reality, overdue months, subcontract rollup, workforce allocation.

Advance contracts (`contract_type_id IN (6,7)`) are **out of scope** — dashboard filters them out.

---

## 2. UI inventory

### Cards (3 rows of 4)

| Card | Formula |
|---|---|
| Total Contracts | `COUNT(*) WHERE contract_type_id NOT IN (6,7)` |
| Total Value | `SUM(contract_value)` |
| Average Value | `AVG(contract_value)` |
| Active | `COUNT(*) WHERE status = 1` |
| Scheduled Total | `SUM(contract_months.amount)` |
| Paid | `SUM(amount) WHERE is_paid = 1` |
| Pending | `SUM(amount) WHERE is_paid = 0` |
| Overdue | `SUM(amount) WHERE is_paid = 0 AND month < today` |
| Subcontracts | `COUNT(*) WHERE parent_contract_id IS NOT NULL` |
| Expired Contracts | `COUNT(*) WHERE end_date < today` |
| Closed Work Orders | `COUNT(*) FROM work_orders WHERE status = 4 AND contract_id ∈ scope` |
| WO Extras Total | `SUM(work_orders.cost) WHERE status = 4` |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Scheduled payments — paid vs pending per month | `YYYY-MM` of `contract_months.month` | stacked `SUM(amount)` |
| Top service providers by value (top 10) | `service_providers.name` | `SUM(contract_value)` |
| Value by contract type | `contract_types.name` | `SUM(contract_value)` |
| Top 10 contracts with overdue | per `contract_number` | `SUM(overdue amount)` |

### Filters

`service_provider_id`, `contract_type_id`, `status`, `date_from`/`date_to` (on `start_date`/`end_date`).
Per-project scope: `contracts.user_id ∈ project_user_ids`.
Single-contract scope: `contracts.id = :id`.

### Table columns

Contract number, type, service provider, start, end, timeline %, value, paid amount, overdue count, status.

---

## 3. Source map

### 3.1 Primary — `contracts`

| Target | Source | Notes |
|---|---|---|
| `contract_id` | `contracts.id` | PK |
| `contract_number` | `.contract_number` | |
| `parent_contract_id` | `.parent_contract_id` | self-FK — subcontracts |
| `owner_user_id` | `.user_id` | project admin user |
| `service_provider_id` | `.service_provider_id` | FK → `dim_service_provider` |
| `contract_type_id` | `.contract_type_id` | FK → `dim_contract_type`. **Filter out 6,7.** |
| `warehouse_id` | `.warehouse_id` | |
| `warehouse_owner` | `.warehouse_owner` | |
| `start_date` | `.start_date` | |
| `end_date` | `.end_date` | |
| `contract_value` | `.contract_value` | FLOAT → NUMERIC(18,2) |
| `retention_percent` | `.retention_percent` | DECIMAL(5,2) |
| `discount_percent` | `.discount_percent` | DECIMAL(5,2) |
| `spare_parts_included` | `.spare_parts_included` | enum('yes','no') → boolean |
| `allow_subcontract` | `.allow_subcontract` | tinyint → boolean |
| `workers_count` | `.workers_count` | |
| `supervisor_count` | `.supervisor_count` | |
| `administrator_count` | `.administrator_count` | |
| `engineer_count` | `.engineer_count` | |
| `comment` | `.comment` | text |
| `file_path` | `.file_path` | |
| `status` | `.status` | tinyint — 1 = active |
| `is_deleted` | `.is_deleted` | enum → boolean |
| `created_at` | `.created_at` | cursor |
| `modified_at` | `.modified_at` | cursor |

#### CSV columns — exploded into bridges

| Source | Bridge | Dim |
|---|---|---|
| `contracts.region_id` (CSV) | `bridge_contract_region` | `dim_region` |
| `contracts.city_id` (CSV) | `bridge_contract_city` | `dim_city` |
| `contracts.asset_categories` (CSV) | (reuse `contract_asset_categories` junction below) | `dim_asset_category` |
| `contracts.asset_names` (CSV) | `bridge_contract_asset_name` | `dim_asset_name` |

`contracts.properties` column is legacy — use `contract_property_buildings` junction instead.

### 3.2 Supporting tables

| Table | DWH target |
|---|---|
| `contract_types` | `dim_contract_type` |
| `contract_property_buildings` | `bridge_contract_property_building` |
| `contract_asset_categories` | `bridge_contract_asset_category` (carries `priority_id` too) |
| `contract_priorities` | `fact_contract_priority` |
| `contract_months` | `fact_contract_month` |
| `contract_payrolls` | `fact_contract_payroll` |
| `contract_payroll_rejections` | `fact_contract_payroll_rejection` |
| `contract_payroll_types` | `dim_payroll_type` |
| `contract_documents` | `fact_contract_document` |
| `contract_inspection_reports` | `fact_contract_inspection_report` |
| `contract_performance_indicators` | `fact_contract_kpi` |
| `contract_service_kpi` | `fact_contract_service_kpi` |
| `contract_usable_items` | `bridge_contract_item` |
| `mapping_osool_akaunting` | `dim_akaunting_map` (for bill/invoice link resolution) |
| `service_providers` | `dim_service_provider` (already in doc #1) |

### 3.3 WO extras

Reuses `fact_work_order` from doc #1 — filtered by `contract_id ∈ scope` and `status_code = 4`.

---

## 4. Target Postgres schema

### 4.1 New dims

```sql
CREATE TABLE marts.dim_contract_type (
    contract_type_id  BIGINT PRIMARY KEY,
    name              TEXT NOT NULL,
    slug              TEXT,
    is_advance        BOOLEAN GENERATED ALWAYS AS (contract_type_id IN (6,7)) STORED,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_payroll_type (
    payroll_type_id   INT PRIMARY KEY,
    name              TEXT NOT NULL,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_akaunting_map (
    osool_document_id     BIGINT NOT NULL,
    document_type         TEXT   NOT NULL,         -- 'invoice','bill','payment'
    akaunting_document_id BIGINT NOT NULL,
    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (osool_document_id, document_type)
);
```

### 4.2 `dim_contract` — SCD Type 2

Contract value, status, and end_date change over a contract's life. Those changes matter for historical reporting ("what was the value of this contract on 2025-06-01?"), so this dim is SCD2.

```sql
CREATE TYPE marts.file_status_enum AS ENUM ('Pending','Review','Approved','Rejected');

CREATE TABLE marts.dim_contract (
    contract_sk           BIGSERIAL PRIMARY KEY,            -- surrogate key
    contract_id           BIGINT NOT NULL,                  -- natural key (source)
    valid_from            TIMESTAMPTZ NOT NULL,
    valid_to              TIMESTAMPTZ NOT NULL DEFAULT 'infinity',
    is_current            BOOLEAN NOT NULL DEFAULT TRUE,

    contract_number       TEXT,
    parent_contract_id    BIGINT,                           -- NULL except for subcontracts
    owner_user_id         BIGINT REFERENCES marts.dim_user(user_id),
    service_provider_id   BIGINT REFERENCES marts.dim_service_provider(sp_id),
    contract_type_id      BIGINT REFERENCES marts.dim_contract_type(contract_type_id),

    start_date            DATE,
    end_date              DATE,

    contract_value        NUMERIC(18,2) NOT NULL DEFAULT 0,
    retention_percent     NUMERIC(5,2)  NOT NULL DEFAULT 0,
    discount_percent      NUMERIC(5,2)  NOT NULL DEFAULT 0,
    spare_parts_included  BOOLEAN NOT NULL DEFAULT FALSE,
    allow_subcontract     BOOLEAN NOT NULL DEFAULT FALSE,

    workers_count         INT NOT NULL DEFAULT 0,
    supervisor_count      INT NOT NULL DEFAULT 0,
    administrator_count   INT NOT NULL DEFAULT 0,
    engineer_count        INT NOT NULL DEFAULT 0,

    comment               TEXT,
    file_path             TEXT,

    status                SMALLINT NOT NULL DEFAULT 0,
    is_active             BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
    is_deleted            BOOLEAN NOT NULL DEFAULT FALSE,

    source_updated_at     TIMESTAMPTZ NOT NULL,
    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now(),

    UNIQUE (contract_id, valid_from)
);

-- Only one current row per natural key
CREATE UNIQUE INDEX ux_dim_contract_current
    ON marts.dim_contract(contract_id) WHERE is_current;

CREATE INDEX ix_dim_contract_sp      ON marts.dim_contract(service_provider_id);
CREATE INDEX ix_dim_contract_type    ON marts.dim_contract(contract_type_id);
CREATE INDEX ix_dim_contract_owner   ON marts.dim_contract(owner_user_id);
CREATE INDEX ix_dim_contract_parent  ON marts.dim_contract(parent_contract_id);
CREATE INDEX ix_dim_contract_natural ON marts.dim_contract(contract_id, valid_from DESC);
```

### 4.3 Bridges (CSV exploded + junctions)

```sql
CREATE TABLE marts.bridge_contract_region (
    contract_id BIGINT NOT NULL,
    region_id   INT    NOT NULL REFERENCES marts.dim_region(region_id) ON DELETE CASCADE,
    PRIMARY KEY (contract_id, region_id)
);

CREATE TABLE marts.bridge_contract_city (
    contract_id BIGINT NOT NULL,
    city_id     BIGINT NOT NULL REFERENCES marts.dim_city(city_id) ON DELETE CASCADE,
    PRIMARY KEY (contract_id, city_id)
);

CREATE TABLE marts.bridge_contract_asset_category (
    contract_id       BIGINT NOT NULL,
    asset_category_id BIGINT NOT NULL REFERENCES marts.dim_asset_category(asset_category_id) ON DELETE CASCADE,
    priority_id       BIGINT REFERENCES marts.dim_priority(priority_id),
    PRIMARY KEY (contract_id, asset_category_id)
);

CREATE TABLE marts.bridge_contract_asset_name (
    contract_id   BIGINT NOT NULL,
    asset_name_id BIGINT NOT NULL REFERENCES marts.dim_asset_name(asset_name_id) ON DELETE CASCADE,
    PRIMARY KEY (contract_id, asset_name_id)
);

CREATE TABLE marts.bridge_contract_property_building (
    contract_id BIGINT NOT NULL,
    building_id BIGINT NOT NULL REFERENCES marts.dim_property_building(building_id) ON DELETE CASCADE,
    PRIMARY KEY (contract_id, building_id)
);

CREATE TABLE marts.bridge_contract_item (
    contract_id     BIGINT NOT NULL,
    item_id         BIGINT NOT NULL,             -- external Akaunting id
    company_id      BIGINT,
    warehouse_id    BIGINT,
    PRIMARY KEY (contract_id, item_id)
);
```

None of these bridges reference `dim_contract.contract_sk` — they target the **natural key** `contract_id`. This keeps SCD2 churn from rewriting every bridge row on every contract update.

### 4.4 Facts

```sql
-- Priority SLA per contract
CREATE TABLE marts.fact_contract_priority (
    id                     BIGINT PRIMARY KEY,
    contract_id            BIGINT NOT NULL,
    priority_id            BIGINT REFERENCES marts.dim_priority(priority_id),
    service_window         INT,
    service_window_type    marts.wo_time_unit_enum,    -- reuse from doc #1
    response_time          NUMERIC,
    response_time_type     marts.wo_time_unit_enum,
    created_at             TIMESTAMPTZ NOT NULL,
    loaded_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fcp_contract ON marts.fact_contract_priority(contract_id);

-- Monthly payment schedule (partitioned)
CREATE TABLE marts.fact_contract_month (
    contract_month_id      BIGINT NOT NULL,
    contract_id            BIGINT NOT NULL,
    user_id                BIGINT,
    month                  DATE NOT NULL,                -- coerced from VARCHAR(255)
    amount                 NUMERIC(18,2) NOT NULL DEFAULT 0,
    is_paid                BOOLEAN NOT NULL DEFAULT FALSE,
    is_extended_contract   BOOLEAN NOT NULL DEFAULT FALSE,
    bill_id                BIGINT,                       -- Akaunting bill id via dim_akaunting_map
    created_at             TIMESTAMPTZ NOT NULL,
    source_updated_at      TIMESTAMPTZ,
    loaded_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (contract_month_id, month)
) PARTITION BY RANGE (month);

CREATE TABLE marts.fact_contract_month_y2024 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
CREATE TABLE marts.fact_contract_month_y2025 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
CREATE TABLE marts.fact_contract_month_y2026 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
CREATE TABLE marts.fact_contract_month_y2027 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');

CREATE INDEX ix_fcm_contract ON marts.fact_contract_month(contract_id);
CREATE INDEX ix_fcm_unpaid   ON marts.fact_contract_month(month) WHERE NOT is_paid;

-- Payroll workflow (one row per uploaded payroll file)
CREATE TABLE marts.fact_contract_payroll (
    payroll_id              BIGINT PRIMARY KEY,
    contract_id             BIGINT NOT NULL,
    payroll_type_id         INT REFERENCES marts.dim_payroll_type(payroll_type_id),
    payroll_type_label      TEXT,
    project_user_id         BIGINT REFERENCES marts.dim_user(user_id),
    service_provider_id     BIGINT REFERENCES marts.dim_service_provider(sp_id),
    payroll_group_id        TEXT,
    file_path               TEXT,
    file_status             marts.file_status_enum NOT NULL,
    rejection_reason        TEXT,
    scheduled               TEXT,
    archived                BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL,
    source_updated_at       TIMESTAMPTZ,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fcpr_contract ON marts.fact_contract_payroll(contract_id);
CREATE INDEX ix_fcpr_status   ON marts.fact_contract_payroll(file_status);

CREATE TABLE marts.fact_contract_payroll_rejection (
    rejection_id            BIGINT PRIMARY KEY,
    payroll_id              BIGINT REFERENCES marts.fact_contract_payroll(payroll_id) ON DELETE CASCADE,
    file_status             marts.file_status_enum,
    rejection_reason        TEXT,
    created_at              TIMESTAMPTZ NOT NULL,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Documents
CREATE TABLE marts.fact_contract_document (
    document_id             BIGINT PRIMARY KEY,
    contract_id             BIGINT NOT NULL,
    document_type_id        INT,
    file_path               TEXT,
    file_status             marts.file_status_enum,
    archived                BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fcd_contract ON marts.fact_contract_document(contract_id);

-- Inspection reports (JSON file_paths kept as JSONB array)
CREATE TABLE marts.fact_contract_inspection_report (
    report_id               BIGINT PRIMARY KEY,
    contract_id             BIGINT NOT NULL,
    report_type_id          INT,
    schedule_type_id        INT,
    file_status             marts.file_status_enum,
    file_paths              JSONB NOT NULL DEFAULT '[]'::jsonb,
    archived                BOOLEAN NOT NULL DEFAULT FALSE,
    created_at              TIMESTAMPTZ NOT NULL,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fcir_contract ON marts.fact_contract_inspection_report(contract_id);

-- KPIs
CREATE TABLE marts.fact_contract_kpi (
    kpi_id                  BIGINT PRIMARY KEY,
    contract_id             BIGINT NOT NULL,
    performance_indicator   JSONB,
    range_id                INT,
    created_at              TIMESTAMPTZ NOT NULL,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fck_contract ON marts.fact_contract_kpi(contract_id);
CREATE INDEX ix_fck_pi_gin   ON marts.fact_contract_kpi USING GIN (performance_indicator);

-- Per-service KPI with price + weights
CREATE TABLE marts.fact_contract_service_kpi (
    id                      BIGINT PRIMARY KEY,
    contract_id             BIGINT NOT NULL,
    service_id              BIGINT NOT NULL,
    performance_indicator   JSONB,                      -- KPI weights/thresholds
    price                   NUMERIC(18,2),
    description             TEXT,
    created_at              TIMESTAMPTZ NOT NULL,
    source_updated_at       TIMESTAMPTZ,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_fcsk_contract ON marts.fact_contract_service_kpi(contract_id);
CREATE INDEX ix_fcsk_pi_gin   ON marts.fact_contract_service_kpi USING GIN (performance_indicator);
```

### 4.5 Raw landing

```sql
CREATE TABLE raw.contracts              (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_types         (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_months        (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_priorities    (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_asset_categories (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_property_buildings (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_usable_items  (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_payrolls      (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_payroll_rejections (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_payroll_types (id INT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_documents     (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_inspection_reports (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_performance_indicators (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.contract_service_kpi   (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
CREATE TABLE raw.mapping_osool_akaunting (
    osool_document_id BIGINT, document_type TEXT, payload JSONB NOT NULL,
    ingested_at TIMESTAMPTZ DEFAULT now(),
    PRIMARY KEY (osool_document_id, document_type)
);
```

---

## 5. ETL transforms

### 5.1 Filter
- Drop `contracts.is_deleted='yes'` rows.
- Drop `contract_type_id IN (6,7)` (advance contracts) unless you explicitly want them in a separate dashboard — they're filtered at dashboard level too but safer to exclude at ingest.
- Drop `contract_service_kpi` rows where `deleted_at IS NOT NULL`.

### 5.2 CSV → bridges
```sql
-- region
INSERT INTO marts.bridge_contract_region (contract_id, region_id)
SELECT :contract_id, unnest(string_to_array(NULLIF(:region_id_csv, ''), ','))::INT
ON CONFLICT DO NOTHING;
```
Same pattern for `city_id`, `asset_categories`, `asset_names`.
On every contract upsert: **delete bridge rows for that contract then re-insert** in a transaction.

### 5.3 `contract_months.month` parsing
Source is `VARCHAR(255)`, typically `"YYYY-MM-DD"`. Coerce:
```sql
CASE
  WHEN month ~ '^\d{4}-\d{2}-\d{2}$' THEN month::DATE
  WHEN month ~ '^\d{4}-\d{2}$'       THEN (month || '-01')::DATE
  ELSE NULL
END
```
Rows with unparsable month go to `raw.contract_months_dlq`.

### 5.4 SCD2 merge on `dim_contract`

For each incoming source row:
1. Read current row: `SELECT * FROM dim_contract WHERE contract_id = :id AND is_current`.
2. Compare the SCD2 tracked attributes (see list below). If unchanged → update non-tracked attributes in place + bump `source_updated_at` + `loaded_at`. No new version.
3. If changed:
   ```sql
   UPDATE marts.dim_contract
   SET valid_to = :source_updated_at, is_current = FALSE
   WHERE contract_id = :id AND is_current;

   INSERT INTO marts.dim_contract
       (contract_id, valid_from, valid_to, is_current, ... all attrs)
   VALUES
       (:id, :source_updated_at, 'infinity', TRUE, ...);
   ```
4. **SCD2-tracked attributes**: `contract_value`, `retention_percent`, `discount_percent`, `end_date`, `status`, `service_provider_id`, `workers_count`, `supervisor_count`, `administrator_count`, `engineer_count`.
5. Non-tracked (just overwrite on current): `comment`, `file_path`, `spare_parts_included`, `allow_subcontract`.

### 5.5 `contract_asset_categories` joint bridge
Carries `(contract_id, asset_category_id, priority_id)`. Insert into `bridge_contract_asset_category` — upsert on `(contract_id, asset_category_id)` replacing `priority_id`.

### 5.6 Akaunting bill links
`contract_months.bill_id` IS **not** a `bills` PK directly; resolve via `mapping_osool_akaunting`:
```sql
SELECT akaunting_document_id
FROM marts.dim_akaunting_map
WHERE osool_document_id = :contract_month_id AND document_type = 'invoice';
```
Render in UI as a link to the Akaunting invoice.

### 5.7 Booleans / enums
`spare_parts_included` yes/no → boolean. `allow_subcontract` tinyint → boolean. `file_status` strings → ENUM; unknown → reject to DLQ.

### 5.8 KPI JSON
`contract_service_kpi.performance_indicator` is source JSON. Store as `JSONB`, GIN indexed for key-based queries:
```sql
SELECT * FROM fact_contract_service_kpi
WHERE performance_indicator @> '{"weight": 20}';
```

### 5.9 Upsert keys
- `dim_contract`: SCD2 logic, natural key = `contract_id`.
- Facts: PK = source id; upsert on `ON CONFLICT (pk)`.
- Bridges: delete-then-insert per `contract_id` in one transaction.

---

## 6. Incremental load

### 6.1 Cursors (independent per table)
```
contracts.modified_at                > :c_contracts    - 10 min
contract_months.updated_at           > :c_months       - 10 min
contract_priorities.created_at       > :c_priorities   - 10 min   (no modified column in source)
contract_payrolls.updated_at         > :c_payrolls     - 10 min
contract_documents.updated_at        > :c_documents    - 10 min
contract_inspection_reports.updated_at > :c_insp       - 10 min
contract_service_kpi.updated_at      > :c_kpi          - 10 min
```

### 6.2 Hard delete
Per endpoint: `deleted_ids[]`. For `dim_contract`, deletion keeps historic versions but flags:
```sql
UPDATE marts.dim_contract SET is_deleted = TRUE
WHERE contract_id = ANY($1);
```
The dashboard filters `NOT is_deleted AND is_current`.

Bridges cascade on `contract_id` — run `DELETE FROM bridge_* WHERE contract_id = ANY($1)`.

### 6.3 Partition maintenance
Monthly cron adds next year to `fact_contract_month`.

---

## 7. Materialized views

### 7.1 Contract-level cards
```sql
CREATE MATERIALIZED VIEW reports.mv_contract_totals AS
SELECT
    dc.owner_user_id,
    dc.service_provider_id,
    dc.contract_type_id,
    COUNT(*)                                              AS total_contracts,
    SUM(dc.contract_value)                                AS total_value,
    AVG(dc.contract_value)                                AS avg_value,
    COUNT(*) FILTER (WHERE dc.is_active)                  AS active_count,
    COUNT(*) FILTER (WHERE dc.parent_contract_id IS NOT NULL) AS subcontract_count,
    COUNT(*) FILTER (WHERE dc.end_date < CURRENT_DATE)    AS expired_count
FROM marts.dim_contract dc
WHERE dc.is_current AND NOT dc.is_deleted
GROUP BY CUBE(dc.owner_user_id, dc.service_provider_id, dc.contract_type_id);

CREATE UNIQUE INDEX ix_mv_ct ON reports.mv_contract_totals
    (owner_user_id, service_provider_id, contract_type_id);
```

### 7.2 Payment schedule rollup (monthly + per-contract)
```sql
CREATE MATERIALIZED VIEW reports.mv_contract_payment_schedule AS
SELECT
    dc.owner_user_id,
    fcm.contract_id,
    to_char(fcm.month, 'YYYY-MM') AS year_month,
    COALESCE(SUM(fcm.amount), 0)                                           AS scheduled,
    COALESCE(SUM(fcm.amount) FILTER (WHERE fcm.is_paid), 0)                AS paid,
    COALESCE(SUM(fcm.amount) FILTER (WHERE NOT fcm.is_paid), 0)            AS pending,
    COALESCE(SUM(fcm.amount) FILTER (WHERE NOT fcm.is_paid AND fcm.month < CURRENT_DATE), 0) AS overdue_amount,
    COUNT(*) FILTER (WHERE NOT fcm.is_paid AND fcm.month < CURRENT_DATE)   AS overdue_count
FROM marts.fact_contract_month fcm
JOIN marts.dim_contract dc ON dc.contract_id = fcm.contract_id AND dc.is_current
WHERE NOT dc.is_deleted
GROUP BY CUBE(dc.owner_user_id, fcm.contract_id, to_char(fcm.month, 'YYYY-MM'));

CREATE UNIQUE INDEX ix_mv_cps ON reports.mv_contract_payment_schedule(owner_user_id, contract_id, year_month);
```

### 7.3 WO extras per contract
```sql
CREATE MATERIALIZED VIEW reports.mv_contract_wo_extras AS
SELECT
    wo.contract_id,
    COUNT(*) FILTER (WHERE wo.status_code = 4)                AS closed_wos,
    COALESCE(SUM(wo.cost) FILTER (WHERE wo.status_code = 4), 0) AS extras_total,
    COALESCE(SUM(wo.cost), 0)                                 AS total_cost,
    to_char(date_trunc('month', wo.created_at), 'YYYY-MM')    AS year_month
FROM marts.fact_work_order wo
WHERE wo.contract_id IS NOT NULL
GROUP BY CUBE(wo.contract_id, to_char(date_trunc('month', wo.created_at), 'YYYY-MM'));

CREATE UNIQUE INDEX ix_mv_cwx ON reports.mv_contract_wo_extras(contract_id, year_month);
```

### 7.4 Top overdue contracts
```sql
CREATE MATERIALIZED VIEW reports.mv_contract_top_overdue AS
SELECT
    fcm.contract_id,
    dc.contract_number,
    dc.service_provider_id,
    COUNT(*)                   AS overdue_months,
    SUM(fcm.amount)            AS overdue_amount
FROM marts.fact_contract_month fcm
JOIN marts.dim_contract dc ON dc.contract_id = fcm.contract_id AND dc.is_current
WHERE NOT fcm.is_paid
  AND fcm.month < CURRENT_DATE
  AND NOT dc.is_deleted
GROUP BY fcm.contract_id, dc.contract_number, dc.service_provider_id
ORDER BY overdue_amount DESC;

CREATE INDEX ix_mv_cto ON reports.mv_contract_top_overdue(overdue_amount DESC);
```

### 7.5 Payroll workflow rollup
```sql
CREATE MATERIALIZED VIEW reports.mv_contract_payroll_status AS
SELECT
    p.contract_id,
    p.file_status,
    COUNT(*) AS cnt
FROM marts.fact_contract_payroll p
GROUP BY p.contract_id, p.file_status;
```

All refreshed `CONCURRENTLY` each 30-min cycle. Aging-sensitive views (§7.2/§7.3/§7.4) also refresh on an hourly cron to cover dates rolling over.

---

## 8. API contract

### 8.1 Endpoints

```
POST /api/dwh/ingest/contract-types
POST /api/dwh/ingest/contract-payroll-types
POST /api/dwh/ingest/mapping-osool-akaunting
POST /api/dwh/ingest/contracts
POST /api/dwh/ingest/contract-property-buildings
POST /api/dwh/ingest/contract-asset-categories
POST /api/dwh/ingest/contract-priorities
POST /api/dwh/ingest/contract-months
POST /api/dwh/ingest/contract-usable-items
POST /api/dwh/ingest/contract-payrolls
POST /api/dwh/ingest/contract-payroll-rejections
POST /api/dwh/ingest/contract-documents
POST /api/dwh/ingest/contract-inspection-reports
POST /api/dwh/ingest/contract-performance-indicators
POST /api/dwh/ingest/contract-service-kpi
```

Call order per cycle: dims (contract-types, payroll-types, akaunting-map) → `contracts` → its junctions/facts.

### 8.2 Contract row

CSV columns are pre-parsed to arrays (like doc #4):

```json
{
  "id": 128,
  "contract_number": "CONT0128",
  "parent_contract_id": null,
  "user_id": 45,
  "service_provider_id": 12,
  "contract_type_id": 2,
  "warehouse_id": 9,
  "warehouse_owner": "in_house",
  "start_date": "2025-01-01",
  "end_date": "2026-12-31",
  "contract_value": 480000.00,
  "retention_percent": 10.00,
  "discount_percent": 0.00,
  "spare_parts_included": "yes",
  "allow_subcontract": 1,
  "workers_count": 20,
  "supervisor_count": 3,
  "administrator_count": 1,
  "engineer_count": 2,
  "comment": "Maintenance contract for Tower A",
  "file_path": "contracts/128.pdf",
  "region_id":         [1, 2],
  "city_id":           [12, 13],
  "asset_categories":  [7, 8, 11],
  "asset_names":       [22, 23],
  "status": 1,
  "is_deleted": "no",
  "created_at": "2024-12-15T09:00:00Z",
  "modified_at": "2026-04-10T12:00:00Z"
}
```

### 8.3 Contract-months row

```json
{
  "id": 801,
  "contract_id": 128,
  "user_id": 45,
  "month": "2026-04-01",
  "amount": "20000.00",
  "is_paid": 0,
  "is_extended_contract": 0,
  "bill_id": 55501,
  "created_at": "2024-12-15T09:05:00Z",
  "updated_at": "2026-04-10T12:00:00Z"
}
```

### 8.4 Contract-priorities row

```json
{
  "id": 301,
  "user_id": 45,
  "contract_id": 128,
  "contract_number": "CONT0128",
  "priority_id": 3,
  "service_window": 24,
  "response_time": 4,
  "service_window_type": "hours",
  "response_time_type": "hours",
  "created_at": "2024-12-15T09:10:00Z"
}
```

### 8.5 Contract-payrolls row

```json
{
  "id": 4501,
  "contract_id": 128,
  "contract_payroll_type_id": 2,
  "contract_payroll_type": "Monthly",
  "file_path": "payrolls/128/2026-04.pdf",
  "file_status": "Approved",
  "rejection_reason": null,
  "project_user_id": 45,
  "service_provider_id": 12,
  "scheduled": "2026-04-30",
  "archived": 0,
  "payroll_group_id": "pg-128-202604",
  "created_at": "2026-05-01T09:00:00Z",
  "updated_at": "2026-05-03T10:00:00Z"
}
```

### 8.6 Akaunting mapping row

```json
{ "osool_document_id": 801, "document_type": "invoice", "akaunting_document_id": 55501 }
```

### 8.7 Contract-service-kpi row

```json
{
  "id": 901,
  "contract_id": 128,
  "service_id": 7,
  "performance_indicator": {
    "weights": { "uptime": 0.4, "response": 0.3, "quality": 0.3 },
    "penalty_percent": 5,
    "range_buckets": [{"min":0,"max":60,"deduction":0.3}]
  },
  "price": 12000.00,
  "description": "HVAC maintenance KPI",
  "created_at": "2024-12-15T09:20:00Z",
  "updated_at": "2025-01-01T00:00:00Z",
  "deleted_at": null
}
```

---

## 9. Validation checks

```yaml
models:
  - name: dim_contract
    columns:
      - name: contract_sk
        tests: [unique, not_null]
      - name: service_provider_id
        tests:
          - relationships: { to: ref('dim_service_provider'), field: sp_id, severity: warn }
      - name: contract_type_id
        tests:
          - relationships: { to: ref('dim_contract_type'), field: contract_type_id }
    tests:
      - dbt_utils.expression_is_true: { expression: "contract_value >= 0" }
      - dbt_utils.expression_is_true: { expression: "valid_from < valid_to" }
      - dbt_utils.expression_is_true:
          expression: "retention_percent BETWEEN 0 AND 100"
      - dbt_utils.unique_combination_of_columns:
          combination_of_columns: [contract_id, valid_from]

  - name: fact_contract_month
    columns:
      - name: contract_id
        tests: [not_null]
      - name: month
        tests: [not_null]
    tests:
      - dbt_utils.expression_is_true: { expression: "amount >= 0" }
      - dbt_utils.unique_combination_of_columns:
          combination_of_columns: [contract_month_id, month]

  - name: fact_contract_payroll
    columns:
      - name: payroll_id
        tests: [unique, not_null]
      - name: file_status
        tests:
          - accepted_values:
              values: [Pending, Review, Approved, Rejected]
```

Operational alerts:
- Row in `raw.contract_months_dlq` > 0 → parse failure, human review.
- `sum(fact_contract_month.amount) / dim_contract.contract_value` > 1.10 per contract → budget mis-match alert (schedule exceeds value).
- Contracts with active status but `end_date < today − 60` → stale status flag.
- `dim_akaunting_map` lookup miss rate > 5% on `contract_months.bill_id` → broken bill links.
- SCD2 "current" rows > 1 per `contract_id` → integrity failure (partial index should prevent this; alert if triggered).

---

## 10. Open questions

1. Which `dim_contract` attributes are **really** SCD2-worthy? The current list is generous; narrowing it reduces row count.
2. `contract_months.month` format drift — confirm ETL parser covers all real-world values (some rows may be `YYYY-MM` only).
3. Advance contracts (types 6, 7): include in the warehouse as a separate fact, or skip entirely? Current: skip at ingest.
4. KPI penalty math (§6.4 in the original spec) — should we compute and store a `fact_contract_month_kpi_deduction` pre-calculated, or keep it as a UI-side computation?
5. `contracts.comment` is free text; is PII possible? If yes, it needs a review before going into dashboards.
6. `contract_inspection_reports.file_paths` JSON schema — array of strings, array of objects? Currently stored as raw JSONB.
7. `mapping_osool_akaunting` — does the source API give us the mapping rows, or must we query Akaunting directly? Assumed source gives them.
8. `work_orders.contract_id = 0` vs NULL — confirm which sentinel is used so the WO-extras rollup joins correctly.
9. Subcontract aggregation — should parent contract's "total cost" roll up children's values? Currently flat; parent dashboard does not sum children.
10. `contract_service_kpi.service_id` — what table is `service_id` FK-ing into? (`asset_categories.id`? `contract_asset_categories.id`?) Must confirm before the KPI view ships.

---

## File location

Saved as `docs/dwh/06-contracts.md`. Last dashboard: `07-overview.md` — the landing-page rollup that reuses everything above.

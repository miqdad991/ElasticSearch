# DWH Dashboard Spec — Assets

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

---

## 1. Dashboard summary

Covered UI:
- `/project-dashboard/assets` — assets belonging to users of the currently selected project.

Decisions supported: inventory composition, asset status health, warranty expiry, category/building distribution, maintenance cost rollup per asset (via `fact_work_order`).

---

## 2. UI inventory

### Cards

| Card | Formula |
|---|---|
| Total Assets | `COUNT(*)` |
| Categories | `COUNT(DISTINCT asset_category_id)` |
| Buildings | `COUNT(DISTINCT property_buildings.building_name)` |
| With Status | `COUNT(*) WHERE asset_status IS NOT NULL AND asset_status <> ''` |
| No Status | `COUNT(*) WHERE asset_status IS NULL OR asset_status = ''` |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Assets per month | `YYYY-MM` of `created_at` | `COUNT(*)` |
| By category (top 15) | `asset_categories.asset_category` | `COUNT(*)` |
| By status | `asset_statuses.name` (or literal if numeric FK fails) | `COUNT(*)` |
| By building (top 15) | `property_buildings.building_name` | `COUNT(*)` |
| By asset name (top 15) | `asset_names.asset_name` | `COUNT(*)` |

### Filters

`asset_status`, `asset_category_id`, `building_id`, `asset_name_id`, `created_at` range.
Scope: `assets.user_id ∈ project_user_ids`.

### Table columns

Asset tag, asset name, category, status, property, building, floor, room, created.

---

## 3. Source map

### 3.1 Primary — `assets`

| Target | Source | Notes |
|---|---|---|
| `asset_id` | `assets.id` | PK |
| `asset_tag` | `assets.asset_tag` | |
| `asset_symbol` | `assets.asset_symbol` | |
| `asset_number` | `assets.asset_number` | |
| `barcode_value` | `assets.barcode_value` | (drop `barcode_img_str`, `barcode` blob) |
| `owner_user_id` | `assets.user_id` | resolves project via `user_projects` |
| `property_id` | `assets.property_id` | FK → `properties.id` (yes, this one is at the property level) |
| `building_id` | `assets.building_id` | FK → `property_buildings.id` |
| `unit_id` | `assets.unit_id` | |
| `floor` | `assets.floor` | |
| `room` | `assets.room` | |
| `asset_category_id` | `assets.asset_category_id` | FK → `dim_asset_category` |
| `asset_name_id` | `assets.asset_name_id` | FK → `dim_asset_name` |
| `asset_status_raw` | `assets.asset_status` | `VARCHAR(20)` — sometimes contains an `asset_statuses.id` as a string, sometimes a free-text label |
| `asset_status_id` | derived | `CAST(asset_status AS INT)` when numeric |
| `model_number` | `assets.model_number` | |
| `manufacturer_name` | `assets.manufacturer_name` | |
| `purchase_date` | `assets.purchase_date` | |
| `purchase_amount` | `assets.purchase_amount` | `DECIMAL(15,2)` |
| `warranty_duration_months` | `assets.warranty_duration_months` | |
| `warranty_end_date` | `assets.warranty_end_date` | |
| `asset_damage_date` | `assets.asset_damage_date` | |
| `usage_threshold` | `assets.usage_threshold` | |
| `threshold_unit_value` | `assets.threshold_unit_value` | enum('days','hours') |
| `hours_per_day` | `assets.hours_per_day` | |
| `days_per_week` | `assets.days_per_week` | |
| `usage_start_at` | `assets.usage_start_at` | |
| `last_usage_reset_at` | `assets.last_usage_reset` | |
| `linked_wo` | `assets.linked_wo` | tinyint → boolean |
| `warehouse_id` | `assets.warehouse_id` | |
| `inventory_id` | `assets.inventory_id` | |
| `converted_assets` | `assets.converted_assets` | |
| `related_to` | `assets.related_to` | default 2; domain-meaningful int |
| `created_at` | `assets.created_at` | cursor |
| `modified_at` | `assets.modified_at` | cursor |
| (filter) | `is_deleted='yes'` OR `deleted_at IS NOT NULL` | |

### 3.2 Lookups (reused from earlier docs)
- `dim_asset_category` (doc #1)
- `dim_asset_name` (doc #1)
- `dim_property` (doc #2)
- `dim_property_building` (doc #2)
- `dim_user` (doc #1)

### 3.3 New lookup needed

`asset_statuses` — `id`, `name`, `color`, `user_id`, `is_deleted`, `deleted_at`. Stand-alone `dim_asset_status`.

---

## 4. Target Postgres schema

### 4.1 New dim

```sql
CREATE TABLE marts.dim_asset_status (
    asset_status_id   INT PRIMARY KEY,          -- asset_statuses.id
    name              TEXT NOT NULL,
    color             TEXT,
    owner_user_id     BIGINT REFERENCES marts.dim_user(user_id),
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 4.2 Fact table (grain = 1 asset)

Assets are long-lived entities, but we model them as a fact because (a) the dashboard aggregates across many dimensions, (b) we need per-asset time-series joins with `fact_work_order`, and (c) keeping them in a dim would bloat every lookup.

```sql
CREATE TYPE marts.asset_threshold_unit_enum AS ENUM ('days','hours');

CREATE TABLE marts.fact_asset (
    asset_id              BIGINT PRIMARY KEY,           -- assets.id
    asset_tag             VARCHAR(150) NOT NULL,
    asset_symbol          TEXT,
    asset_number          TEXT,
    barcode_value         TEXT,

    owner_user_id         BIGINT REFERENCES marts.dim_user(user_id),
    property_id           BIGINT REFERENCES marts.dim_property(property_id),
    building_id           BIGINT REFERENCES marts.dim_property_building(building_id),
    unit_id               INT,
    floor                 TEXT,
    room                  TEXT,

    asset_category_id     BIGINT REFERENCES marts.dim_asset_category(asset_category_id),
    asset_name_id         BIGINT REFERENCES marts.dim_asset_name(asset_name_id),
    asset_status_id       INT    REFERENCES marts.dim_asset_status(asset_status_id),
    asset_status_raw      TEXT,                         -- keep original for audit

    model_number          TEXT,
    manufacturer_name     TEXT,
    purchase_date         DATE,
    purchase_amount       NUMERIC(15,2),

    warranty_duration_months INT,
    warranty_end_date     DATE,
    asset_damage_date     DATE,

    usage_threshold       INT,
    threshold_unit_value  marts.asset_threshold_unit_enum,
    hours_per_day         INT,
    days_per_week         SMALLINT,
    usage_start_at        TIMESTAMPTZ,
    last_usage_reset_at   TIMESTAMPTZ,

    linked_wo             BOOLEAN NOT NULL DEFAULT FALSE,
    warehouse_id          BIGINT,
    inventory_id          INT,
    converted_assets      INT NOT NULL DEFAULT 0,
    related_to            SMALLINT,

    has_status            BOOLEAN GENERATED ALWAYS AS
                              (asset_status_raw IS NOT NULL AND asset_status_raw <> '') STORED,
    is_under_warranty     BOOLEAN GENERATED ALWAYS AS
                              (warranty_end_date IS NOT NULL AND warranty_end_date >= CURRENT_DATE) STORED,

    created_date_key      DATE GENERATED ALWAYS AS ((created_at AT TIME ZONE 'UTC')::date) STORED,
    created_at            TIMESTAMPTZ NOT NULL,
    source_updated_at     TIMESTAMPTZ,
    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (created_date_key) REFERENCES marts.dim_date(date_key)
);

CREATE INDEX ix_fa_owner      ON marts.fact_asset(owner_user_id);
CREATE INDEX ix_fa_property   ON marts.fact_asset(property_id);
CREATE INDEX ix_fa_building   ON marts.fact_asset(building_id);
CREATE INDEX ix_fa_category   ON marts.fact_asset(asset_category_id);
CREATE INDEX ix_fa_name       ON marts.fact_asset(asset_name_id);
CREATE INDEX ix_fa_status     ON marts.fact_asset(asset_status_id);
CREATE INDEX ix_fa_created    ON marts.fact_asset(created_date_key);
CREATE INDEX ix_fa_warranty   ON marts.fact_asset(warranty_end_date)
    WHERE warranty_end_date IS NOT NULL;
```

Not partitioned — assets tend to number in the low hundred-thousands; partitioning isn't worth the overhead. Revisit if the fact exceeds ~10M rows.

### 4.3 Raw landing

```sql
CREATE TABLE raw.assets (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.asset_statuses (
    id INT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- raw.asset_categories / raw.asset_names already declared in doc #1.
```

---

## 5. ETL transforms

1. **Filter** — drop where `is_deleted='yes'` OR `deleted_at IS NOT NULL`.
2. **`asset_status` parsing** — source stores a `VARCHAR(20)` that may be:
   - numeric (the `asset_statuses.id`) → cast to INT and store in `asset_status_id`, copy raw into `asset_status_raw`.
   - empty / NULL → `asset_status_id = NULL`, `asset_status_raw = NULL`.
   - free-text → `asset_status_id = NULL`, `asset_status_raw = <text>`.
   Implementation: `CASE WHEN asset_status ~ '^\d+$' THEN asset_status::INT END`.
3. **Booleans** — `linked_wo` tinyint → boolean; `hide_asset_symbol` dropped.
4. **Empty string → NULL** across VARCHAR columns.
5. **Drop bulky / unused columns** — `barcode`, `barcode_img_str`, `damage_images`, `auto_pmwo_data`, `gen_image`, `general_information`, `previous_status_before_repair`. Keep only what the dashboard + analytics need.
6. **Timestamps → TIMESTAMPTZ** (assume UTC).
7. **Upsert on `asset_id`**.

---

## 6. Incremental load

### 6.1 Cursor
```
modified_at > last_cursor - 10 minutes
```

### 6.2 Upsert
```sql
INSERT INTO marts.fact_asset (...) VALUES (...)
ON CONFLICT (asset_id) DO UPDATE SET
    asset_tag        = EXCLUDED.asset_tag,
    asset_status_id  = EXCLUDED.asset_status_id,
    asset_status_raw = EXCLUDED.asset_status_raw,
    asset_category_id= EXCLUDED.asset_category_id,
    asset_name_id    = EXCLUDED.asset_name_id,
    building_id      = EXCLUDED.building_id,
    property_id      = EXCLUDED.property_id,
    warranty_end_date= EXCLUDED.warranty_end_date,
    purchase_amount  = EXCLUDED.purchase_amount,
    ...
    source_updated_at= EXCLUDED.source_updated_at,
    loaded_at        = now();
```

### 6.3 Hard delete
`DELETE FROM marts.fact_asset WHERE asset_id = ANY($1)` from `deleted_ids`.
**Cascade:** if a WO references a deleted asset, keep the WO — FK is nullable.

---

## 7. Materialized views

### 7.1 Asset KPI cube (drives cards + small charts)

```sql
CREATE MATERIALIZED VIEW reports.mv_asset_kpis AS
SELECT
    a.owner_user_id,
    a.asset_category_id,
    a.building_id,
    a.asset_status_id,
    a.asset_name_id,
    to_char(a.created_at, 'YYYY-MM') AS year_month,
    COUNT(*)                               AS asset_count,
    COUNT(*) FILTER (WHERE a.has_status)   AS with_status,
    COUNT(*) FILTER (WHERE NOT a.has_status) AS without_status,
    COUNT(*) FILTER (WHERE a.is_under_warranty) AS under_warranty,
    COUNT(DISTINCT a.asset_category_id)    AS distinct_categories,
    COUNT(DISTINCT a.building_id)          AS distinct_buildings
FROM marts.fact_asset a
GROUP BY CUBE(
    a.owner_user_id, a.asset_category_id, a.building_id,
    a.asset_status_id, a.asset_name_id,
    to_char(a.created_at, 'YYYY-MM')
);

CREATE UNIQUE INDEX ix_mv_asset_kpis ON reports.mv_asset_kpis
    (owner_user_id, asset_category_id, building_id, asset_status_id, asset_name_id, year_month);
```

### 7.2 Asset × WO maintenance cost rollup (not on the current dashboard, but cheap to add)

```sql
CREATE MATERIALIZED VIEW reports.mv_asset_wo_cost AS
SELECT
    a.asset_id,
    a.asset_tag,
    a.owner_user_id,
    a.asset_category_id,
    a.building_id,
    COUNT(wo.wo_id)                         AS wo_count,
    COUNT(wo.wo_id) FILTER (WHERE wo.status_code = 4) AS closed_wos,
    COALESCE(SUM(wo.cost), 0)               AS lifetime_cost
FROM marts.fact_asset a
LEFT JOIN marts.fact_work_order wo
       ON wo.asset_name_id     = a.asset_name_id
      AND wo.asset_category_id = a.asset_category_id
      AND wo.property_id       = a.building_id      -- WO.property_id is actually a building_id
GROUP BY a.asset_id, a.asset_tag, a.owner_user_id, a.asset_category_id, a.building_id;

CREATE UNIQUE INDEX ix_mv_asset_wo_cost ON reports.mv_asset_wo_cost(asset_id);
```

> The join between `fact_asset` and `fact_work_order` is approximate because the source data has no direct `asset_id` FK on `work_orders`. Accept the `category × name × building` approximation unless a proper `wo.asset_id` is added upstream.

Refresh both concurrently after each load cycle.

---

## 8. API contract

### 8.1 Endpoints

```
POST /api/dwh/ingest/asset-statuses
POST /api/dwh/ingest/assets
```

Call order inside one cycle: asset-statuses → (asset-categories, asset-names, property-buildings from earlier docs) → assets.

### 8.2 Asset row

```json
{
  "id": 8001,
  "asset_tag": "FCU-0001",
  "user_id": 45,
  "property_id": 2001,
  "building_id": 5001,
  "unit_id": 12,
  "floor": "3",
  "room": "302",
  "asset_category_id": 7,
  "asset_name_id": 22,
  "asset_number": "A-8001",
  "barcode_value": "BC-FCU-0001",
  "asset_symbol": "FCU",
  "asset_status": "4",
  "model_number": "XP-200",
  "manufacturer_name": "Carrier",
  "purchase_date": "2022-06-01",
  "purchase_amount": "2500.00",
  "warranty_duration_months": 36,
  "warranty_end_date": "2025-06-01",
  "asset_damage_date": null,
  "usage_threshold": 1000,
  "threshold_unit_value": "hours",
  "hours_per_day": 8,
  "days_per_week": 5,
  "usage_start_at": "2022-06-15T08:00:00Z",
  "last_usage_reset": null,
  "linked_wo": 1,
  "warehouse_id": 9,
  "inventory_id": 101,
  "converted_assets": 0,
  "related_to": 2,
  "is_deleted": "no",
  "created_at": "2022-06-10T09:30:00Z",
  "modified_at": "2026-04-01T12:00:00Z"
}
```

### 8.3 Asset status row

```json
{
  "id": 4,
  "name": "Operational",
  "color": "#22c55e",
  "user_id": 45,
  "is_deleted": "no"
}
```

---

## 9. Validation checks

```yaml
models:
  - name: fact_asset
    columns:
      - name: asset_id
        tests: [not_null, unique]
      - name: asset_tag
        tests: [not_null]
      - name: owner_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }
      - name: asset_category_id
        tests:
          - relationships: { to: ref('dim_asset_category'), field: asset_category_id, severity: warn }
      - name: asset_name_id
        tests:
          - relationships: { to: ref('dim_asset_name'), field: asset_name_id, severity: warn }
      - name: building_id
        tests:
          - relationships: { to: ref('dim_property_building'), field: building_id, severity: warn }
      - name: asset_status_id
        tests:
          - relationships: { to: ref('dim_asset_status'), field: asset_status_id, severity: warn }
    tests:
      - dbt_utils.expression_is_true: { expression: "purchase_amount IS NULL OR purchase_amount >= 0" }
      - dbt_utils.expression_is_true: { expression: "warranty_duration_months IS NULL OR warranty_duration_months >= 0" }
      - dbt_utils.expression_is_true:
          expression: "warranty_end_date IS NULL OR purchase_date IS NULL OR warranty_end_date >= purchase_date"
          severity: warn
```

Operational alerts: `asset_status_id` unresolved rate > 20% (indicates status column storing free text instead of FK), `building_id` NULL rate > 30%.

---

## 10. Open questions

1. `assets.asset_status` is a `VARCHAR(20)` — confirm the parsing rule (`numeric → FK id, otherwise text`). Any other conventions?
2. Source has no `assets.service_provider_id`. For maintenance cost rollup (§7.2) we approximate via category + name + building. Acceptable?
3. `assets.property_id` points at `properties.id`, while `assets.building_id` points at `property_buildings.id`. Confirm — we index both.
4. `converted_assets` and `related_to` semantics unclear. Kept as raw numbers.
5. Is there a meaningful distinction between `NULL purchase_date` and `'0000-00-00'` in the source? Transform treats both as NULL.
6. `auto_pmwo_data` (large text, likely JSON) not loaded. If PM automation analytics are needed, parse later.
7. Do you want a denormalized `mv_asset_report` view that pre-joins category/name/building/status text labels for the UI? Probably yes — trivial to add once the above lands.

---

## File location

Saved as `docs/dwh/03-assets.md`. Next: `04-users.md`.

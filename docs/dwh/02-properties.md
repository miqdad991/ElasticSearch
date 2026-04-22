# DWH Dashboard Spec — Properties

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

---

## 1. Dashboard summary

Covered UIs:
- `/project-dashboard/properties` — properties owned by users of the selected project
- `/properties-dashboard` — all properties across all projects

Decisions supported: property portfolio composition, geographic distribution, building count, contract coverage, total budget allocated via `commercial_contracts`.

---

## 2. UI inventory

### Cards (both dashboards)

| Card | Formula |
|---|---|
| Total Properties | `COUNT(*)` in scope |
| Total Buildings | `SUM(properties.buildings_count)` |
| Single Buildings | `COUNT(*) WHERE property_type='building'` |
| Complexes | `COUNT(*) WHERE property_type='complex'` |
| Active | `COUNT(*) WHERE status=1` |
| Total Contracts | `COUNT(commercial_contracts.*)` in scope |
| Rent | `COUNT(commercial_contracts.*) WHERE contract_type='rent'` |
| Lease | `COUNT(commercial_contracts.*) WHERE contract_type='lease'` |
| Active Contracts | `COUNT(commercial_contracts.*) WHERE status=1` |
| Auto-Renewal | `COUNT(commercial_contracts.*) WHERE auto_renewal=1` |
| Total Assets | `COUNT(assets.*)` in scope |
| Total Work Orders | `COUNT(work_orders.*)` in scope |
| Total Budget | `SUM(commercial_contracts.amount)` |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Properties per month | `YYYY-MM` of `created_at` | `COUNT(*)` |
| By property type | `property_type` (building/complex) | `COUNT(*)` |
| By status | `status` (Active/Inactive) | `COUNT(*)` |
| By region (top 15) | `regions.name` | `COUNT(*)` |
| By city (top 15) | `cities.name_en` | `COUNT(*)` |
| Contracts by type | `commercial_contracts.contract_type` | `COUNT(*)` |
| Ejar sync status | `commercial_contracts.ejar_sync_status` | `COUNT(*)` |
| Contracts per property (top 15) | `properties.project` | `COUNT(*)` |

### Filters

`status`, `property_type`, `location_type`, `region_id`, `city_id`, `created_at` date range. Per-project scope implicit via `properties.user_id ∈ project_user_ids`.

### Table columns

Properties list: property name, tag, type, buildings count, floors, units, region, city, status, created.
Recent contracts: reference, name, type, property, building, start/end, amount+currency, ejar.

---

## 3. Source map

### 3.1 Primary sources

| Target | Source | Notes |
|---|---|---|
| `property_id` | `properties.id` | PK |
| `user_id` | `properties.user_id` | owner; resolves project via `user_projects` |
| `property_name` | `properties.project` | yes — column is literally named `project` in source |
| `property_tag` | `properties.property_tag` | |
| `property_type` | `properties.property_type` | enum('building','complex') |
| `location_type` | `properties.location_type` | enum('single_location','multiple_location') |
| `region_id` | `properties.region_id` | FK → `regions.id` |
| `city_id` | `properties.city_id` | FK → `cities.id` |
| `district_name` | `properties.district_name` | |
| `street_name` | `properties.street_name` | |
| `postal_code` | `properties.postal_code` | |
| `building_number` | `properties.building_number` | |
| `buildings_count` | `properties.buildings_count` | |
| `total_floors` | `properties.total_floors` | |
| `units_per_floor` | `properties.units_per_floor` | |
| `total_units` | `properties.total_units` | |
| `property_usage` | `properties.property_usage` | |
| `latitude`, `longitude` | `properties.latitude`, `.longitude` | store as NUMERIC, not VARCHAR |
| `established_date` | `properties.established_date` | |
| `awqaf_contains` | `properties.awqaf_contains` | enum('yes','no') → boolean |
| `worker_housing` | `properties.worker_housing` | tinyint → boolean |
| `status` | `properties.status` | |
| `created_at` | `properties.created_at` | cursor |
| `modified_at` | `properties.modified_at` | cursor |
| (filter out) | `properties.is_deleted='yes'` OR `deleted_at IS NOT NULL` | |

### 3.2 Reference dims

- `regions` — `id`, `name`, `name_ar`, `code`, `country_id`
- `cities` — `id`, `name_en`, `name_ar`, `code`, `postal_code`, `region_id`
- `property_buildings` — `id`, `property_id`, `building_name`, `rooms_count`, coords, is_deleted — **already declared in doc #1**, reused

### 3.3 Related facts (already in the warehouse from doc #1/#3)

- `commercial_contracts` → `fact_commercial_contract` (full model in doc #5 Billing)
- `work_orders` → `fact_work_order` (doc #1)
- `assets` → `fact_asset` (doc #3)

This doc only adds the **property dim** and its support dims. Facts reuse them via FK.

---

## 4. Target Postgres schema

### 4.1 New dims for this dashboard

```sql
CREATE TABLE marts.dim_region (
    region_id         INT PRIMARY KEY,
    name              TEXT NOT NULL,
    name_ar           TEXT,
    code              TEXT,
    country_id        INT,
    latitude          NUMERIC(10,6),
    longitude         NUMERIC(10,6),
    status            SMALLINT,
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_city (
    city_id           BIGINT PRIMARY KEY,
    name_en           TEXT NOT NULL,
    name_ar           TEXT,
    code              TEXT,
    postal_code       TEXT,
    region_id         INT REFERENCES marts.dim_region(region_id),
    country_id        BIGINT,
    status            SMALLINT,
    is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
    loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_dim_city_region ON marts.dim_city(region_id);
```

### 4.2 Property dim (fact-table-shaped, since this is the core of the dashboard)

Modeled as a **dim** because properties are static-ish lookups in every other fact, but this dashboard uses it as the grain. We avoid a separate `fact_property` and query the dim directly.

```sql
CREATE TYPE marts.property_type_enum     AS ENUM ('building','complex');
CREATE TYPE marts.location_type_enum     AS ENUM ('single_location','multiple_location');

CREATE TABLE marts.dim_property (
    property_id         BIGINT PRIMARY KEY,             -- properties.id
    owner_user_id       BIGINT REFERENCES marts.dim_user(user_id),
    property_name       TEXT NOT NULL,                  -- properties.project
    property_tag        TEXT,
    property_number     TEXT,
    compound_name       TEXT,

    property_type       marts.property_type_enum,
    location_type       marts.location_type_enum,
    property_usage      TEXT,

    region_id           INT REFERENCES marts.dim_region(region_id),
    city_id             BIGINT REFERENCES marts.dim_city(city_id),
    district_name       TEXT,
    street_name         TEXT,
    postal_code         TEXT,
    building_number     TEXT,

    latitude            NUMERIC(10,6),
    longitude           NUMERIC(10,6),
    location_label      TEXT,

    buildings_count     INT NOT NULL DEFAULT 0,
    actual_buildings_added INT,
    total_floors        INT,
    units_per_floor     INT,
    total_units         INT,

    established_date    DATE,
    awqaf_contains      BOOLEAN NOT NULL DEFAULT FALSE,
    worker_housing      BOOLEAN NOT NULL DEFAULT FALSE,
    agreement_status    TEXT,
    contract_type       TEXT,

    status              SMALLINT,                       -- 1 = active
    is_active           BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
    is_deleted          BOOLEAN NOT NULL DEFAULT FALSE,

    created_at          TIMESTAMPTZ NOT NULL,
    source_updated_at   TIMESTAMPTZ,
    loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX ix_dim_property_owner    ON marts.dim_property(owner_user_id);
CREATE INDEX ix_dim_property_region   ON marts.dim_property(region_id);
CREATE INDEX ix_dim_property_city     ON marts.dim_property(city_id);
CREATE INDEX ix_dim_property_type     ON marts.dim_property(property_type);
CREATE INDEX ix_dim_property_created  ON marts.dim_property(created_at);
```

### 4.3 Building dim (re-declared here for completeness; doc #1 referenced it but didn't fill all columns)

```sql
-- (REPLACE the stub from doc #1)
DROP TABLE IF EXISTS marts.dim_property_building CASCADE;

CREATE TABLE marts.dim_property_building (
    building_id         BIGINT PRIMARY KEY,             -- property_buildings.id
    property_id         BIGINT REFERENCES marts.dim_property(property_id),
    building_name       TEXT NOT NULL,
    building_tag        TEXT,
    rooms_count         SMALLINT NOT NULL DEFAULT 0,
    use_building        SMALLINT,                       -- 1 = in use
    district_name       TEXT,
    street_name         TEXT,
    latitude            NUMERIC(10,6),
    longitude           NUMERIC(10,6),
    location_label      TEXT,
    barcode_value       TEXT,
    ownership_document_type   TEXT,
    ownership_document_number TEXT,
    ownership_issue_date      DATE,
    is_deleted          BOOLEAN NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMPTZ NOT NULL,
    source_updated_at   TIMESTAMPTZ,
    loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ix_dim_building_property ON marts.dim_property_building(property_id);
```

> **Migration note:** doc #1's `dim_property_building` stub is superseded by this one. `fact_work_order.property_id` must now reference `dim_property_building(building_id)`. Update that FK when applying this migration. (The field in `work_orders.property_id` actually points at `property_buildings.id`, not `properties.id` — keep the semantics.)

### 4.4 Raw landing tables

```sql
CREATE TABLE raw.properties (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.regions (
    id INT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.cities (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- raw.property_buildings already declared in doc #1; extend payload for the columns above.
```

---

## 5. ETL transforms

1. **Filter** — drop where `is_deleted='yes'` OR `deleted_at IS NOT NULL`.
2. **Enum → boolean**
   - `awqaf_contains` `'yes'`/`'no'` → `awqaf_contains BOOLEAN`
   - `worker_housing` tinyint → boolean
3. **String → numeric for coordinates** — source stores lat/lng as `VARCHAR(25)`; cast via `NULLIF(trim(latitude), '')::NUMERIC`.
4. **`property_type`, `location_type`** — mapped 1:1 to Postgres ENUMs; unknown → NULL (log warn).
5. **Rename** — `properties.project` → `dim_property.property_name` (source name is misleading; keep the renamed one).
6. **Drop columns** that are UI-only / unused: `property_image` blobs, `ownership_scanned_documents` (large text), `property_layout_files`. Keep the metadata only.
7. **Empty strings → NULL** for optional VARCHARs.
8. **Timestamp coercion** — `created_at TIMESTAMP` assumed UTC; store as `TIMESTAMPTZ`.
9. **Dim dedup** — upsert on PK. No SCD here — property address changes are rare; if history is needed, promote `dim_property` to SCD2 later.

---

## 6. Incremental load

### 6.1 Cursor
```
modified_at > last_cursor - 10 minutes
```
Fallback for rows lacking `modified_at`: use `created_at`.

### 6.2 Upsert
```sql
INSERT INTO marts.dim_property (...) VALUES (...)
ON CONFLICT (property_id) DO UPDATE SET
    property_name    = EXCLUDED.property_name,
    property_type    = EXCLUDED.property_type,
    region_id        = EXCLUDED.region_id,
    city_id          = EXCLUDED.city_id,
    buildings_count  = EXCLUDED.buildings_count,
    status           = EXCLUDED.status,
    is_deleted       = EXCLUDED.is_deleted,
    ... (all other cols)
    source_updated_at = EXCLUDED.source_updated_at,
    loaded_at         = now();
```

### 6.3 Hard delete
`deleted_ids[]` from API → `DELETE FROM marts.dim_property WHERE property_id = ANY($1)`.
**Cascade check:** fact tables referencing `dim_property` must either be hard-deleted first or have `ON DELETE SET NULL`. Given facts can outlive a property row, use `ON DELETE SET NULL` on the FK.

---

## 7. Materialized views

### 7.1 Main KPI view
```sql
CREATE MATERIALIZED VIEW reports.mv_property_kpis AS
SELECT
    p.owner_user_id,
    p.region_id,
    p.city_id,
    p.property_type,
    p.location_type,
    to_char(p.created_at, 'YYYY-MM')       AS year_month,
    COUNT(*)                               AS property_count,
    COALESCE(SUM(p.buildings_count),0)     AS buildings_count,
    COUNT(*) FILTER (WHERE p.is_active)    AS active_count,
    COUNT(*) FILTER (WHERE p.property_type='building') AS building_count,
    COUNT(*) FILTER (WHERE p.property_type='complex')  AS complex_count
FROM marts.dim_property p
WHERE NOT p.is_deleted
GROUP BY CUBE(p.owner_user_id, p.region_id, p.city_id, p.property_type, p.location_type, to_char(p.created_at, 'YYYY-MM'));

CREATE UNIQUE INDEX ix_mv_property_kpis ON reports.mv_property_kpis
    (owner_user_id, region_id, city_id, property_type, location_type, year_month);
```

### 7.2 Property × contracts rollup
Joins `dim_property` to `fact_commercial_contract` (see doc #5) for the "Contracts per Property" chart and Total Budget card.
```sql
CREATE MATERIALIZED VIEW reports.mv_property_contract_rollup AS
SELECT
    p.property_id,
    p.property_name,
    p.owner_user_id,
    COUNT(c.*)                                        AS contract_count,
    COUNT(c.*) FILTER (WHERE c.contract_type='rent')  AS rent_count,
    COUNT(c.*) FILTER (WHERE c.contract_type='lease') AS lease_count,
    COUNT(c.*) FILTER (WHERE c.is_active)             AS active_contracts,
    COUNT(c.*) FILTER (WHERE c.auto_renewal)          AS auto_renewal_count,
    COALESCE(SUM(c.amount),0)                         AS total_budget
FROM marts.dim_property p
LEFT JOIN marts.fact_commercial_contract c ON c.property_id = p.property_id AND NOT c.is_deleted
WHERE NOT p.is_deleted
GROUP BY p.property_id, p.property_name, p.owner_user_id;

CREATE UNIQUE INDEX ix_mv_prop_contract ON reports.mv_property_contract_rollup(property_id);
```

Refresh both concurrently after each 30-min load.

---

## 8. API contract

### 8.1 Endpoints (same envelope as doc #1 §8.1)

```
POST /api/dwh/ingest/regions
POST /api/dwh/ingest/cities
POST /api/dwh/ingest/properties
POST /api/dwh/ingest/property-buildings
```

Call order inside one cycle: regions → cities → properties → property-buildings → (any dependent facts).

### 8.2 Property row

```json
{
  "id": 2001,
  "user_id": 45,
  "project": "Riyadh Tower",
  "property_tag": "RT-01",
  "property_number": "P-2001",
  "compound_name": "Olaya Complex",
  "property_type": "complex",
  "location_type": "multiple_location",
  "property_usage": "commercial",
  "region_id": 1,
  "city_id": 12,
  "district_name": "Al Olaya",
  "street_name": "King Fahd Rd",
  "postal_code": "11564",
  "building_number": "2042",
  "latitude": "24.7136",
  "longitude": "46.6753",
  "location": "Riyadh, Saudi Arabia",
  "buildings_count": 3,
  "actual_buildings_added": 3,
  "total_floors": 12,
  "units_per_floor": 4,
  "total_units": 48,
  "established_date": "2018-01-15",
  "awqaf_contains": "no",
  "worker_housing": 0,
  "agreement_status": "active",
  "contract_type": "rent",
  "status": 1,
  "is_deleted": "no",
  "created_at": "2018-01-15T00:00:00Z",
  "modified_at": "2026-04-10T10:00:00Z"
}
```

### 8.3 Region / City rows

```json
{ "id": 1, "name": "Riyadh Region", "name_ar": "منطقة الرياض", "code": "RI", "country_id": 1, "status": 1, "is_deleted": "no" }
```

```json
{ "id": 12, "name_en": "Riyadh", "name_ar": "الرياض", "code": "RUH", "postal_code": "11564", "region_id": 1, "country_id": 1, "status": 1, "is_deleted": "no" }
```

### 8.4 Property building row

```json
{
  "id": 5001,
  "property_id": 2001,
  "building_name": "Tower A",
  "building_tag": "A",
  "rooms_count": 20,
  "use_building": 1,
  "district_name": "Al Olaya",
  "street_name": "King Fahd Rd",
  "latitude": "24.7137",
  "longitude": "46.6754",
  "location": "Tower A, Riyadh",
  "barcode_value": "BC-A-5001",
  "ownership_document_type": "title_deed",
  "ownership_document_number": "TD-9001",
  "ownership_issue_date": "2018-02-01",
  "is_deleted": "no",
  "created_at": "2018-02-01T00:00:00Z",
  "modified_at": "2026-04-10T10:00:00Z"
}
```

---

## 9. Validation checks

```yaml
models:
  - name: dim_property
    columns:
      - name: property_id
        tests: [not_null, unique]
      - name: property_name
        tests: [not_null]
      - name: region_id
        tests:
          - relationships: { to: ref('dim_region'), field: region_id, severity: warn }
      - name: city_id
        tests:
          - relationships: { to: ref('dim_city'), field: city_id, severity: warn }
      - name: owner_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }
    tests:
      - dbt_utils.expression_is_true: { expression: "latitude BETWEEN -90 AND 90" }
      - dbt_utils.expression_is_true: { expression: "longitude BETWEEN -180 AND 180" }
      - dbt_utils.expression_is_true: { expression: "buildings_count >= 0" }
```

Operational alerts: city lookup miss rate > 1% in a batch, rows with NULL `region_id` after load > 5%.

---

## 10. Open questions

1. Source `latitude`/`longitude` are `VARCHAR(25) NOT NULL`. Some rows may contain empty strings or `"0,0"` — confirm before enabling range checks.
2. `properties.project` (property name) is `VARCHAR(200) NOT NULL` but often contains descriptive text rather than a name — do you want `property_name` and `property_description` separated? Assumed no for now.
3. Geographic enrichment: do you want `dim_region.country_id` fleshed out into a full `dim_country` or inlined as a text column? Assumed inline for now.
4. `properties.ownership_scanned_documents` is a huge TEXT (sometimes base64). Skipped entirely — confirm.
5. Some rows use `awqaf_contains` as `NULL` not `'no'`. Transform treats NULL as FALSE; flag if you need tri-state.
6. Should `dim_property` become SCD2 if an owner/region change matters for history? Current scope keeps Type 1.
7. `property_buildings.rooms` is `longtext` (JSON array of room records). Not loaded now — if needed, becomes `fact_property_building_room` later.
8. `property_buildings.property_id` references `properties.id` (parent). Cascading deletes: when a property is deleted, does the source also delete its buildings? If not, we need a cleanup query.

---

## File location

Saved as `docs/dwh/02-properties.md`. Next: `03-assets.md`.

# DWH Dashboard Spec — Billing & Receivables (Lease / Tenant)

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

---

## 1. Dashboard summary

Covered UIs:
- `/billing-dashboard` — all lease/rent contracts + installments across the platform.
- `/project-dashboard/billing` — same, scoped to the selected project via `commercial_contracts.project_id`.

Decisions supported: tenant receivables health, cash inflow per month, overdue aging, deposit exposure, fee composition (late / brokerage / retainer), Ejar sync compliance.

**Out of scope here** (covered by doc #6 Contracts): the execution-side `contracts` table + `contract_months` schedule + WO extras. That's a different money domain.

---

## 2. UI inventory

### Cards (4 rows of 4)

| Card | Formula |
|---|---|
| **Row 1** — contracts | |
| Total Contracts | `COUNT(*)` |
| Total Contract Value | `SUM(amount)` |
| Rent Contracts | `COUNT(*) WHERE contract_type='rent'` |
| Lease Contracts | `COUNT(*) WHERE contract_type='lease'` |
| **Row 2** — deposits & fees | |
| Security Deposits | `SUM(security_deposit_amount)` |
| Late Fees | `SUM(late_fees_charge)` |
| Brokerage Fees | `SUM(brokerage_fee)` |
| Retainer Fees | `SUM(retainer_fee)` |
| **Row 3** — installment money | |
| Total Installments | `COUNT(payment_details.*)` |
| Collected | `SUM(amount) WHERE is_paid=1` |
| Outstanding | `SUM(amount) WHERE is_paid=0` |
| Overdue Amount | `SUM(amount) WHERE is_paid=0 AND payment_due_date < today` |
| **Row 4** — counts | |
| Paid Installments | `COUNT(*) WHERE is_paid=1` |
| Unpaid Installments | `COUNT(*) WHERE is_paid=0` |
| Overdue Count | `COUNT(*) WHERE is_paid=0 AND payment_due_date < today` |
| Payment Due (contracts) | `SUM(commercial_contracts.payment_due)` |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Collections vs Outstanding per month | `YYYY-MM` of `payment_due_date` | stacked `SUM(amount)` by paid status |
| Receivables aging | Future / 0-30 / 31-60 / 61-90 / 90+ days overdue | `SUM(amount) WHERE is_paid=0` |
| Contracts by type | `contract_type` | `SUM(amount)` |
| Top 10 tenants by outstanding | `tenant_name` | `SUM(amount) WHERE is_paid=0` |
| Payment methods | `payment_type` of paid installments | `COUNT`, `SUM(amount)` |
| Ejar sync status | `commercial_contracts.ejar_sync_status` | `COUNT(*)` |

### Filters

`contract_type` (rent/lease), `ejar_sync_status`, `project_id`, `created_at` range.
Per-project scope replaces the project filter.

### Tables

- **Overdue Installments** — ref, contract, type, tenant, due date, days overdue, amount.
- **Upcoming Installments** — ref, contract, type, tenant, due date, amount.

---

## 3. Source map

### 3.1 Primary — `commercial_contracts`

| Target | Source | Notes |
|---|---|---|
| `commercial_contract_id` | `commercial_contracts.id` | PK |
| `reference_number` | `.reference_number` | |
| `contract_name` | `.contract_name` | |
| `contract_type` | `.contract_type` | enum('rent','lease'), nullable |
| `tenant_id` | `.tenant_id` | FK → `dim_user` |
| `property_id` | `.property_id` | FK → `dim_property` |
| `building_id` | `.building_id` | FK → `dim_property_building` |
| `unit_id` | `.unit_id` | |
| `project_id` | `.project_id` | FK → `dim_project` |
| `created_by` | `.created_by` | FK → `dim_user` |
| `ejar_contract_id` | `.ejar_contract_id` | |
| `ejar_sync_status` | `.ejar_sync_status` | 4-value enum |
| `calendar_type` | `.calender_type` | source typo `calender_type` |
| `start_date` | `.start_date` | |
| `end_date` | `.end_date` | |
| `signing_date` | `.signing_date` | |
| `payment_date` | `.payment_date` | |
| `payment_interval` | `.payment_interval` | |
| `amount` | `.amount` | BIGINT — cast to NUMERIC |
| `security_deposit_amount` | `.security_deposit_amount` | DECIMAL(8,2) |
| `late_fees_charge` | `.late_fees_charge` | |
| `brokerage_fee` | `.brokerage_fee` | |
| `retainer_fee` | `.retainer_fee` | |
| `payment_due` | `.payment_due` | outstanding on the contract |
| `payment_overdue` | `.payment_overdue` | overdue on the contract |
| `currency` | `.currency` | |
| `lessor_iban` | `.lessor_iban` | **treat as sensitive** (see §A) |
| `issuing_office` | `.issuing_office` | TEXT |
| `status` | `.status` | tinyint — 1 = active |
| `auto_renewal` | `.auto_renewal` | tinyint → boolean |
| `is_unit_applies` | `.is_unit_applies` | tinyint → boolean |
| `is_dynamic_rent_applies` | `.is_dynamic_rent_applies` | tinyint → boolean |
| `created_at` | `.created_at` | cursor |
| `updated_at` | `.updated_at` | cursor |
| (filter) | `.is_deleted='yes'` OR `.deleted_at IS NOT NULL` | |

### 3.2 Primary — `payment_details`

| Target | Source | Notes |
|---|---|---|
| `installment_id` | `payment_details.id` | PK |
| `contract_id` | `.contract_id` | FK → commercial_contracts |
| `payment_ref` | `.payment_ref` | |
| `transaction_id` | `.transaction_id` | |
| `transaction_date` | `.transaction_date` | |
| `lessor_id` | `.lessor_id` | |
| `lessor_name` | `.lessor_name` | denormalized snapshot |
| `tenant_id` | `.tenant_id` | |
| `tenant_name` | `.tenant_name` | denormalized snapshot |
| `start_date` | `.start_date` | period start |
| `installment_end_date` | `.installment_end_date` | period end |
| `date_before_due_date` | `.date_before_due_date` | reminder date |
| `payment_due_date` | `.payment_due_date` | key for aging |
| `original_payment_date` | `.original_payment_date` | |
| `payment_date` | `.payment_date` | actual payment date |
| `is_paid` | `.is_paid` | tinyint → boolean |
| `is_prepayment` | `.is_prepayment` | tinyint → boolean |
| `amount` | `.amount` | BIGINT → NUMERIC |
| `amount_prepayment` | `.amount_prepayment` | |
| `payment_type` | `.payment_type` | free text, normalized in ETL |
| `payment_interval` | `.payment_interval` | |
| `from_bank_account` | `.from_bank_account` | sensitive |
| `to_bank_account` | `.to_bank_account` | sensitive |
| `from_bank_prepayment` | `.from_bank_prepayment` | sensitive |
| `to_bank_prepayment` | `.to_bank_prepayment` | sensitive |
| `payment_type_prepayment` | `.payment_type_prepayment` | |
| `notes` | `.notes` | |
| `notes_prepayment` | `.notes_prepayment` | |
| `receipt_ref` | `.receipt_ref` | |
| `receipt_date` | `.receipt_date` | |
| `updated_by` | `.updated_by` | FK → dim_user |
| `created_at` | `.created_at` | cursor |
| `updated_at` | `.updated_at` | cursor |
| (filter) | `.deleted_at IS NOT NULL` | |

### 3.3 Primary — `lease_contract_details`

| Target | Source | Notes |
|---|---|---|
| `lease_detail_id` | `lease_contract_details.id` | PK |
| `commercial_contract_id` | `.commercial_contract_id` | 1:1 with commercial_contracts |
| `property_id` | `.property_id` | |
| `tenant_id` | `.tenant_id` | |
| `tenant_phone_number` | `.tenant_phone_number` | |
| `tenant_email` | `.tenant_email` | |
| `tenant_cr_id` | `.tenant_cr_id` | commercial registration |
| `landlord_name` | `.landlord_name` | |
| `landlord_phone_number` | `.landlord_phone_number` | |
| `landlord_email` | `.landlord_email` | |
| `landlord_cr_id` | `.landlord_cr_id` | |
| `contract_terms` | `.contract_terms` | longtext; store JSONB if it's JSON, else TEXT |
| `contract_attachments` | `.contract_attachments` | longtext (usually JSON array of URLs) |
| `created_at` | `.created_at` | |
| `updated_at` | `.updated_at` | |

---

## 4. Target Postgres schema

### 4.1 Dims

```sql
CREATE TYPE marts.ejar_status_enum AS ENUM
    ('synced_successfully','pending_sync','failed_sync','not_synced');
CREATE TYPE marts.lease_type_enum AS ENUM ('rent','lease');
CREATE TYPE marts.calendar_enum   AS ENUM ('gregorian','hijri');

-- Tenant dim — a specialized view of dim_user for rented scenarios.
-- Tenants in source are regular rows in `users` with user_type='tenant'. We reuse dim_user and add a tenant-only attribute bag here.
CREATE TABLE marts.dim_tenant (
    tenant_id           BIGINT PRIMARY KEY REFERENCES marts.dim_user(user_id),
    tenant_phone_number TEXT,
    tenant_email        CITEXT,
    tenant_cr_id        TEXT,               -- commercial registration
    loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE marts.dim_landlord (
    landlord_id         BIGSERIAL PRIMARY KEY,
    name                TEXT NOT NULL,
    phone               TEXT,
    email               CITEXT,
    cr_id               TEXT,
    UNIQUE (name, phone, email)
);
```

> `dim_landlord` is a **Type 1 surrogate** dim — source lease records carry landlord details inline, not as an FK. We dedupe by `(name, phone, email)` at load time.

### 4.2 Fact — commercial contract

```sql
CREATE TABLE marts.fact_commercial_contract (
    commercial_contract_id  BIGINT PRIMARY KEY,
    reference_number        TEXT,
    contract_name           TEXT,
    contract_type           marts.lease_type_enum,
    tenant_id               BIGINT REFERENCES marts.dim_tenant(tenant_id),
    landlord_id             BIGINT REFERENCES marts.dim_landlord(landlord_id),
    property_id             BIGINT REFERENCES marts.dim_property(property_id),
    building_id             BIGINT REFERENCES marts.dim_property_building(building_id),
    unit_id                 BIGINT,
    project_id              INT REFERENCES marts.dim_project(project_id),
    created_by              BIGINT REFERENCES marts.dim_user(user_id),

    ejar_contract_id        TEXT,
    ejar_sync_status        marts.ejar_status_enum NOT NULL DEFAULT 'not_synced',
    calendar_type           marts.calendar_enum NOT NULL DEFAULT 'gregorian',

    start_date              DATE,
    end_date                DATE,
    signing_date            DATE,
    payment_date            DATE,
    payment_interval        TEXT,

    amount                  NUMERIC(18,2) NOT NULL DEFAULT 0,
    security_deposit_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
    late_fees_charge        NUMERIC(18,2) NOT NULL DEFAULT 0,
    brokerage_fee           NUMERIC(18,2) NOT NULL DEFAULT 0,
    retainer_fee            NUMERIC(18,2) NOT NULL DEFAULT 0,
    payment_due             NUMERIC(18,2) NOT NULL DEFAULT 0,
    payment_overdue         NUMERIC(18,2) NOT NULL DEFAULT 0,
    currency                CHAR(3),                   -- normalized to ISO 4217 on load

    issuing_office          TEXT,
    lessor_iban_token       TEXT,                      -- see §A
    status                  SMALLINT NOT NULL DEFAULT 0,
    is_active               BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
    auto_renewal            BOOLEAN NOT NULL DEFAULT FALSE,
    is_unit_applies         BOOLEAN NOT NULL DEFAULT FALSE,
    is_dynamic_rent_applies BOOLEAN NOT NULL DEFAULT FALSE,
    is_deleted              BOOLEAN NOT NULL DEFAULT FALSE,

    -- lease-details join (1:1)
    contract_terms_json     JSONB,
    contract_attachments_json JSONB,

    created_at              TIMESTAMPTZ NOT NULL,
    source_updated_at       TIMESTAMPTZ,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX ix_fcc_tenant        ON marts.fact_commercial_contract(tenant_id);
CREATE INDEX ix_fcc_property      ON marts.fact_commercial_contract(property_id);
CREATE INDEX ix_fcc_building      ON marts.fact_commercial_contract(building_id);
CREATE INDEX ix_fcc_project       ON marts.fact_commercial_contract(project_id);
CREATE INDEX ix_fcc_type          ON marts.fact_commercial_contract(contract_type);
CREATE INDEX ix_fcc_ejar          ON marts.fact_commercial_contract(ejar_sync_status);
CREATE INDEX ix_fcc_dates         ON marts.fact_commercial_contract(start_date, end_date);
```

### 4.3 Fact — installments (partitioned)

```sql
CREATE TABLE marts.fact_installment (
    installment_id          BIGINT NOT NULL,
    commercial_contract_id  BIGINT NOT NULL REFERENCES marts.fact_commercial_contract(commercial_contract_id) ON DELETE CASCADE,

    payment_ref             TEXT,
    transaction_id          TEXT,
    transaction_date        DATE,

    lessor_id               BIGINT,
    lessor_name_snapshot    TEXT,
    tenant_id               BIGINT REFERENCES marts.dim_tenant(tenant_id),
    tenant_name_snapshot    TEXT,

    period_start            DATE,
    period_end              DATE,
    date_before_due         DATE,
    payment_due_date        DATE NOT NULL,
    original_payment_date   DATE,
    payment_date            DATE,

    amount                  NUMERIC(18,2) NOT NULL DEFAULT 0,
    amount_prepayment       NUMERIC(18,2) NOT NULL DEFAULT 0,

    is_paid                 BOOLEAN NOT NULL DEFAULT FALSE,
    is_prepayment           BOOLEAN NOT NULL DEFAULT FALSE,
    is_overdue              BOOLEAN GENERATED ALWAYS AS
                                (NOT is_paid AND payment_due_date < CURRENT_DATE) STORED,
    days_overdue            INT GENERATED ALWAYS AS
                                (GREATEST(0, (CURRENT_DATE - payment_due_date))) STORED,

    payment_type            TEXT,
    payment_interval        TEXT,

    from_bank_token         TEXT,                      -- see §A (hashed)
    to_bank_token           TEXT,
    from_bank_prepayment_token TEXT,
    to_bank_prepayment_token TEXT,
    payment_type_prepayment TEXT,

    notes                   TEXT,
    notes_prepayment        TEXT,
    receipt_ref             TEXT,
    receipt_date            DATE,

    updated_by              BIGINT REFERENCES marts.dim_user(user_id),

    created_at              TIMESTAMPTZ NOT NULL,
    source_updated_at       TIMESTAMPTZ,
    loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (installment_id, payment_due_date)
) PARTITION BY RANGE (payment_due_date);

-- initial partitions; cron adds future years
CREATE TABLE marts.fact_installment_y2024 PARTITION OF marts.fact_installment
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
CREATE TABLE marts.fact_installment_y2025 PARTITION OF marts.fact_installment
    FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
CREATE TABLE marts.fact_installment_y2026 PARTITION OF marts.fact_installment
    FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
CREATE TABLE marts.fact_installment_y2027 PARTITION OF marts.fact_installment
    FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');

CREATE INDEX ix_fi_contract     ON marts.fact_installment(commercial_contract_id);
CREATE INDEX ix_fi_tenant       ON marts.fact_installment(tenant_id);
CREATE INDEX ix_fi_due_date     ON marts.fact_installment(payment_due_date);
CREATE INDEX ix_fi_paid         ON marts.fact_installment(is_paid);
CREATE INDEX ix_fi_overdue_open ON marts.fact_installment(payment_due_date)
    WHERE NOT is_paid;
```

> `is_overdue` and `days_overdue` are generated against `CURRENT_DATE` at row read, not insert time — actually Postgres requires generated columns to be immutable, so these must be **virtual** (computed in the `mv_*` instead). Correction shown in §7 — drop those two generated columns from the table DDL above and compute in the materialized view.

### 4.4 Raw landing

```sql
CREATE TABLE raw.commercial_contracts (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.payment_details (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.lease_contract_details (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

---

## 5. ETL transforms

1. **Filter** — drop where `is_deleted='yes'` OR `deleted_at IS NOT NULL` for both tables.
2. **Enum / boolean coercion**
   - `contract_type` NULL stays NULL (dashboard renders as `'unspecified'`).
   - `is_paid`, `is_prepayment`, `auto_renewal`, etc. → boolean.
3. **Amounts** — source stores some as BIGINT; cast to `NUMERIC(18,2)`. Zero any NULL amount columns on insert.
4. **Currency** — normalize to uppercase ISO 4217 (SAR / USD / AED). Unknown → NULL with warn log.
5. **Ejar status** — lowercase + map to ENUM. Unknown → `'not_synced'`.
6. **Landlord dedup** — hash `(name, phone, email)` lowercased; lookup `dim_landlord`, insert if new, reuse the `landlord_id`.
7. **Tenant backfill** — if `tenant_id` present but no `dim_tenant` row, auto-create from `dim_user` (enforces the FK gracefully).
8. **Calendar type typo** — `calender_type` → `calendar_type`.
9. **Lease-details join** — lease_contract_details is 1:1. Flattened into `fact_commercial_contract` columns (`contract_terms_json`, `contract_attachments_json`) at transform time; `contract_terms`/`contract_attachments` parsed as JSON when valid, else wrapped as `{"raw": <text>}`.
10. **IBAN / bank account tokenization** — see §A.
11. **Upsert** on `commercial_contract_id` and `(installment_id, payment_due_date)`.

---

## 6. Incremental load

### 6.1 Cursor (two independent streams)
```
commercial_contracts.updated_at > last_cursor_cc - 10 min
payment_details.updated_at      > last_cursor_pd - 10 min
```
DWH tracks both separately in `dwh.sync_state`.

### 6.2 Upsert
```sql
-- contracts
INSERT INTO marts.fact_commercial_contract (...) VALUES (...)
ON CONFLICT (commercial_contract_id) DO UPDATE SET
    contract_type = EXCLUDED.contract_type,
    amount = EXCLUDED.amount,
    payment_due = EXCLUDED.payment_due,
    payment_overdue = EXCLUDED.payment_overdue,
    ejar_sync_status = EXCLUDED.ejar_sync_status,
    status = EXCLUDED.status,
    is_deleted = EXCLUDED.is_deleted,
    ...
    source_updated_at = EXCLUDED.source_updated_at,
    loaded_at = now();

-- installments
INSERT INTO marts.fact_installment (...) VALUES (...)
ON CONFLICT (installment_id, payment_due_date) DO UPDATE SET
    is_paid = EXCLUDED.is_paid,
    payment_date = EXCLUDED.payment_date,
    amount = EXCLUDED.amount,
    ...
    source_updated_at = EXCLUDED.source_updated_at,
    loaded_at = now();
```

### 6.3 Hard delete
`deleted_ids` in each endpoint's payload. `fact_installment` cascades from the partition key; `fact_commercial_contract` deletion cascades to installments via FK `ON DELETE CASCADE`.

### 6.4 Partition maintenance
Cron adds the next year for `fact_installment` when ≥6 months of lead-time remain.

---

## 7. Materialized views

### 7.1 Header cards (contract-level)
```sql
CREATE MATERIALIZED VIEW reports.mv_billing_contract_totals AS
SELECT
    c.project_id,
    c.contract_type,
    COUNT(*)                                             AS total_contracts,
    COUNT(*) FILTER (WHERE c.contract_type = 'rent')     AS rent_contracts,
    COUNT(*) FILTER (WHERE c.contract_type = 'lease')    AS lease_contracts,
    COUNT(*) FILTER (WHERE c.auto_renewal)               AS auto_renewal,
    COALESCE(SUM(c.amount), 0)                           AS total_value,
    COALESCE(SUM(c.security_deposit_amount), 0)          AS total_security,
    COALESCE(SUM(c.late_fees_charge), 0)                 AS total_late_fees,
    COALESCE(SUM(c.brokerage_fee), 0)                    AS total_brokerage,
    COALESCE(SUM(c.retainer_fee), 0)                     AS total_retainer,
    COALESCE(SUM(c.payment_due), 0)                      AS total_payment_due,
    COALESCE(SUM(c.payment_overdue), 0)                  AS total_payment_overdue
FROM marts.fact_commercial_contract c
WHERE NOT c.is_deleted
GROUP BY CUBE(c.project_id, c.contract_type);

CREATE UNIQUE INDEX ix_mv_bct ON reports.mv_billing_contract_totals(project_id, contract_type);
```

### 7.2 Installment cards + per-month chart
```sql
CREATE MATERIALIZED VIEW reports.mv_billing_installments AS
WITH scoped AS (
    SELECT i.*, c.project_id
    FROM marts.fact_installment i
    JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
    WHERE NOT c.is_deleted
)
SELECT
    project_id,
    to_char(payment_due_date, 'YYYY-MM')               AS due_month,
    COUNT(*)                                           AS total_installments,
    COUNT(*) FILTER (WHERE is_paid)                    AS paid_count,
    COUNT(*) FILTER (WHERE NOT is_paid)                AS unpaid_count,
    COUNT(*) FILTER (WHERE NOT is_paid AND payment_due_date < CURRENT_DATE) AS overdue_count,
    COUNT(*) FILTER (WHERE is_prepayment)              AS prepayments,
    COALESCE(SUM(amount) FILTER (WHERE is_paid), 0)        AS collected,
    COALESCE(SUM(amount) FILTER (WHERE NOT is_paid), 0)    AS outstanding,
    COALESCE(SUM(amount) FILTER (WHERE NOT is_paid AND payment_due_date < CURRENT_DATE), 0) AS overdue_amount
FROM scoped
GROUP BY CUBE(project_id, to_char(payment_due_date, 'YYYY-MM'));

CREATE UNIQUE INDEX ix_mv_bi ON reports.mv_billing_installments(project_id, due_month);
```

### 7.3 Aging buckets
```sql
CREATE MATERIALIZED VIEW reports.mv_billing_aging AS
WITH scoped AS (
    SELECT i.amount, i.payment_due_date, i.is_paid, c.project_id
    FROM marts.fact_installment i
    JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
    WHERE NOT c.is_deleted AND NOT i.is_paid
)
SELECT
    project_id,
    SUM(amount) FILTER (WHERE payment_due_date >= CURRENT_DATE)                         AS future,
    SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 1 AND 30)       AS d30,
    SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 31 AND 60)      AS d60,
    SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 61 AND 90)      AS d90,
    SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) > 90)                   AS d90_plus
FROM scoped
GROUP BY CUBE(project_id);

CREATE UNIQUE INDEX ix_mv_aging ON reports.mv_billing_aging(project_id);
```

### 7.4 Top tenants by outstanding
```sql
CREATE MATERIALIZED VIEW reports.mv_billing_top_tenants AS
SELECT
    c.project_id,
    i.tenant_id,
    COALESCE(u.full_name, i.tenant_name_snapshot, 'Tenant #' || i.tenant_id) AS tenant_label,
    SUM(i.amount) AS outstanding
FROM marts.fact_installment i
JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
LEFT JOIN marts.dim_user u ON u.user_id = i.tenant_id
WHERE NOT c.is_deleted AND NOT i.is_paid
GROUP BY c.project_id, i.tenant_id, u.full_name, i.tenant_name_snapshot;

CREATE INDEX ix_mv_top_tenants ON reports.mv_billing_top_tenants(project_id, outstanding DESC);
```

All refreshed `CONCURRENTLY` after each 30-min load. Aging view must refresh at least once a day to keep bucket accuracy even when no new data lands.

---

## 8. API contract

### 8.1 Endpoints

```
POST /api/dwh/ingest/commercial-contracts
POST /api/dwh/ingest/lease-contract-details
POST /api/dwh/ingest/payment-details
```

Call order: commercial-contracts → lease-contract-details → payment-details.

### 8.2 Commercial contract row

```json
{
  "id": 501,
  "reference_number": "CC-2025-0042",
  "contract_name": "Tower A-12",
  "contract_type": "rent",
  "tenant_id": 4501,
  "property_id": 2001,
  "building_id": 5001,
  "unit_id": 301,
  "project_id": 67,
  "created_by": 45,
  "ejar_contract_id": "EJ-987654",
  "ejar_sync_status": "synced_successfully",
  "calender_type": "gregorian",
  "start_date": "2025-01-01",
  "end_date": "2026-12-31",
  "signing_date": "2024-12-15",
  "payment_date": "2025-01-05",
  "payment_interval": "monthly",
  "amount": 180000,
  "security_deposit_amount": "15000.00",
  "late_fees_charge": "500.00",
  "brokerage_fee": "2500.00",
  "retainer_fee": "1000.00",
  "payment_due": 60000,
  "payment_overdue": 0,
  "currency": "SAR",
  "issuing_office": "Riyadh Main",
  "lessor_iban": "SA0380000000608010167519",
  "status": 1,
  "auto_renewal": 1,
  "is_unit_applies": 0,
  "is_dynamic_rent_applies": 0,
  "is_deleted": "no",
  "created_at": "2024-12-10T08:00:00Z",
  "updated_at": "2026-04-10T11:00:00Z"
}
```

### 8.3 Lease-details row

```json
{
  "id": 1201,
  "commercial_contract_id": 501,
  "property_id": 2001,
  "tenant_id": 4501,
  "tenant_phone_number": "+966500000001",
  "tenant_email": "tenant@example.com",
  "tenant_cr_id": "CR-12345",
  "landlord_name": "Al Khaleej Holdings",
  "landlord_phone_number": "+966500000099",
  "landlord_email": "ops@khaleej.example.com",
  "landlord_cr_id": "CR-0099",
  "contract_terms": "{\"late_fee_grace_days\":5,\"penalty\":\"0.5%\"}",
  "contract_attachments": "[\"https://cdn.example.com/att/1.pdf\"]",
  "created_at": "2024-12-10T08:05:00Z",
  "updated_at": "2024-12-10T08:05:00Z"
}
```

### 8.4 Payment-details (installment) row

```json
{
  "id": 90001,
  "contract_id": 501,
  "payment_ref": "INV-0001",
  "transaction_id": "TRX-778899",
  "transaction_date": "2026-04-05",
  "lessor_id": 32,
  "lessor_name": "Al Khaleej Holdings",
  "tenant_id": 4501,
  "tenant_name": "Ibrahim Al-Harbi",
  "start_date": "2026-04-01",
  "installment_end_date": "2026-04-30",
  "date_before_due_date": "2026-04-05",
  "payment_due_date": "2026-04-10",
  "original_payment_date": "2026-04-10",
  "payment_date": "2026-04-05",
  "is_paid": 1,
  "is_prepayment": 0,
  "amount": 15000,
  "amount_prepayment": 0,
  "payment_type": "bank_transfer",
  "payment_interval": "monthly",
  "from_bank_account": "SA03...7519",
  "to_bank_account":   "SA04...1234",
  "from_bank_prepayment": null,
  "to_bank_prepayment":   null,
  "payment_type_prepayment": null,
  "notes": "Paid in full",
  "notes_prepayment": null,
  "receipt_ref": "R-0001",
  "receipt_date": "2026-04-05",
  "updated_by": 12,
  "created_at": "2026-03-01T00:00:00Z",
  "updated_at": "2026-04-05T13:22:00Z"
}
```

---

## A. Sensitive-field handling

IBANs, bank-account numbers, CR IDs, and bank account tokens are **PII / financial** data. The warehouse stores them tokenized, not in cleartext.

### A.1 Tokenization at load

- `lessor_iban`, `from_bank_account`, `to_bank_account`, `*_bank_prepayment` → stored as `SHA-256(value || per-project salt)` in the `*_token` column.
- Salt lives in `auth.secret.bank_salt` (a config row in the auth DB) so analysts querying `fact_installment` never see the raw value.
- Reverse-lookup requires a separate `pii.bank_lookup(token, raw)` table only the payments app can read.

Schema:
```sql
CREATE SCHEMA IF NOT EXISTS pii;
REVOKE ALL ON SCHEMA pii FROM PUBLIC;

CREATE TABLE pii.bank_lookup (
    token      TEXT PRIMARY KEY,
    raw_value  TEXT NOT NULL,
    first_seen_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Only a dedicated role (`dwh_pii_reader`) gets SELECT on `pii.bank_lookup`. Dashboard users do not.

### A.2 Masking in the UI

When showing `lessor_iban_token` or bank tokens in the dashboards, render the last-4 from the raw value (requires `dwh_pii_reader`) or just `••••` for everyone else.

### A.3 Right-to-erase

`DELETE FROM pii.bank_lookup WHERE token = :t` on tenant request. The `fact_installment` token remains (for audit of cash flow) but is no longer reversible.

---

## 9. Validation checks

```yaml
models:
  - name: fact_commercial_contract
    columns:
      - name: commercial_contract_id
        tests: [not_null, unique]
      - name: tenant_id
        tests:
          - relationships: { to: ref('dim_tenant'), field: tenant_id, severity: warn }
      - name: property_id
        tests:
          - relationships: { to: ref('dim_property'), field: property_id, severity: warn }
      - name: project_id
        tests:
          - relationships: { to: ref('dim_project'), field: project_id, severity: warn }
    tests:
      - dbt_utils.expression_is_true: { expression: "amount >= 0" }
      - dbt_utils.expression_is_true: { expression: "security_deposit_amount >= 0" }
      - dbt_utils.expression_is_true:
          expression: "end_date IS NULL OR start_date IS NULL OR end_date >= start_date"

  - name: fact_installment
    columns:
      - name: installment_id
        tests: [not_null]
      - name: commercial_contract_id
        tests: [not_null, relationships: { to: ref('fact_commercial_contract'), field: commercial_contract_id }]
      - name: payment_due_date
        tests: [not_null]
      - name: amount
        tests: [not_null]
    tests:
      - dbt_utils.expression_is_true: { expression: "amount >= 0" }
      - dbt_utils.unique_combination_of_columns:
          combination_of_columns: [installment_id, payment_due_date]
```

Operational:
- Unpaid installments older than 180 days → alert (collections queue).
- `sum(paid_installments.amount) - sum(commercial_contracts.payment_due - payment_overdue)` diff per contract > 5% → data reconciliation alert.
- Mv refresh age > 45 min.
- Tokenizer failure rate > 0 → security alert.

---

## 10. Open questions

1. Source marks some `commercial_contracts.contract_type` as NULL. Keep NULL or coerce to `'rent'` (heuristic)? Current: keep NULL, dashboard groups under `'unspecified'`.
2. Currencies — source `VARCHAR(255)` with free text (often `"SAR"`, sometimes full name). Confirm upper-case 3-char normalization is correct.
3. `commercial_contracts.amount` is BIGINT in cents or full units? Assuming **major units** (SAR). Verify.
4. Should `amount_prepayment` be part of `collected` in the Collections KPI? Currently no — it's tracked separately.
5. `lease_contract_details.contract_terms` JSON parsing — confirm it's always JSON; if not, we wrap in `{"raw": text}`.
6. PII policy: does IBAN tokenization match your regulator's requirements? If you need FPE (format-preserving encryption) instead of SHA-256, swap the tokenizer.
7. Multi-currency reporting — if contracts span SAR+USD, the Total Budget card sums heterogeneous values. Add FX conversion fact? (Out of current scope.)
8. Deleted `commercial_contracts` cascade-deletes installments — confirm this matches source system behavior.
9. `payment_details.amount_prepayment` vs `amount` when `is_prepayment=1` — is amount 0 in those rows, or duplicated? Needs source sample check.

---

## File location

Saved as `docs/dwh/05-billing.md`. Next: `06-contracts.md` — execution contracts, `contract_months`, WO extras payment tracking, SCD2 dim, subcontracts.

# DWH Dashboard Spec — Project Selector & Project Landing Page

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs
**Delete policy:** hard delete

> Closes the loop on two UIs that the earlier docs only touched indirectly.

---

## 1. Dashboard summary

Covered UIs:
- **`/select-project`** — lists all projects the logged-in admin can enter. Picking one stores `selected_project_id` in the session and unlocks every project-scoped dashboard.
- **`/project-dashboard`** — the per-project landing page. Shows project info, asset categories tied to this project's users, priorities tied to this project's users, project users table, financial overview, and the top overview cards (Total Assets, Total Work Orders, Total Budget, Total Contracts).

---

## 2. UI inventory

### 2.1 `/select-project`

**Table columns per project row:** project name, owner, industry, contract window, status, property count, service-provider count, contract value, payment due, payment overdue, module flags.

All of this is already produced by `reports.mv_project_rollup` (doc #7).
**No new schema, no new ETL, no new endpoints.** The screen is a sorted, filterable read of that mv plus a button that writes `selected_project_id` to the user's session.

Access control: `dim_user.user_type IN ('super_admin','osool_admin','admin','admin_employee')` see every project; all other user types see only projects where `bridge_user_project.user_id = :me`.

### 2.2 `/project-dashboard` landing

| Section | Content | Formula |
|---|---|---|
| Header card | Project name, initials avatar, industry | `dim_project` row |
| Overview cards (4) | Total Assets, Total Work Orders, Total Budget, Total Contracts | `mv_project_landing` (below) |
| Financial Overview (8 cards) | Contract Value, Security Deposits, Payment Due, Payment Overdue, Late Fees, Brokerage Fees, Retainer Fees, Approved Payrolls | `mv_project_landing` (reuses `mv_billing_contract_totals` + `fact_contract_payroll`) |
| Charts | Contract Amount per Month, By Contract Type | `mv_billing_installments` filtered by project |
| Asset Categories tag cloud | Unique `asset_category` names owned by any user in the project | `dim_asset_category` joined via `bridge_user_project` |
| Priorities tag cloud | Unique priority levels owned by any user in the project | `dim_priority` joined via `bridge_user_project` |
| Project Users table | Project's users with type, phone, status | `dim_user` + `bridge_user_project` |

---

## 3. Source map — deltas from earlier docs

The only gap in the existing schemas is that **`priorities.user_id`** and **`asset_categories.user_id`** were dropped on the way into `dim_priority` and `dim_asset_category`. Without those columns, the landing page can't filter either dim by project.

### 3.1 `priorities` — extra column

| Target | Source | Notes |
|---|---|---|
| `owner_user_id` | `priorities.user_id` | FK → `dim_user`; nullable (some rows have no owner) |
| `is_deleted` | `priorities.is_deleted` | → boolean |
| `deleted_at` | `priorities.deleted_at` | |
| `created_at` | `priorities.created_at` | |
| `modified_at` | `priorities.modified_at` | |

### 3.2 `asset_categories` — extra columns

| Target | Source | Notes |
|---|---|---|
| `owner_user_id` | `asset_categories.user_id` | FK → `dim_user`; nullable |
| `status` | `asset_categories.status` | |
| `service_type` | `asset_categories.service_type` | enum('hard','soft') |
| `default_priority_id` | `asset_categories.priority_id` | FK → `dim_priority` |
| `tenant_form_enabled` | `asset_categories.tenant_form_enabled` | tinyint → boolean |
| `is_deleted` | `.is_deleted` | → boolean |
| `deleted_at` | `.deleted_at` | |
| `created_at` | `.created_at` | |
| `modified_at` | `.modified_at` | |

---

## 4. Target Postgres schema — patches

### 4.1 `dim_priority` (patch)

```sql
ALTER TABLE marts.dim_priority
    ADD COLUMN owner_user_id BIGINT REFERENCES marts.dim_user(user_id),
    ADD COLUMN is_deleted    BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN source_updated_at TIMESTAMPTZ,
    ADD COLUMN created_at   TIMESTAMPTZ;

CREATE INDEX ix_dim_priority_owner ON marts.dim_priority(owner_user_id);
```

### 4.2 `dim_asset_category` (patch)

```sql
CREATE TYPE marts.asset_service_type_enum AS ENUM ('hard','soft');

ALTER TABLE marts.dim_asset_category
    ADD COLUMN owner_user_id       BIGINT REFERENCES marts.dim_user(user_id),
    ADD COLUMN service_type        marts.asset_service_type_enum,
    ADD COLUMN default_priority_id BIGINT REFERENCES marts.dim_priority(priority_id),
    ADD COLUMN tenant_form_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN is_deleted          BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN source_updated_at   TIMESTAMPTZ,
    ADD COLUMN created_at          TIMESTAMPTZ;

CREATE INDEX ix_dim_asset_cat_owner    ON marts.dim_asset_category(owner_user_id);
CREATE INDEX ix_dim_asset_cat_service  ON marts.dim_asset_category(service_type);
```

> These replace the stubs in doc #1. The rest of the schema in docs 01–07 is unchanged.

---

## 5. ETL transforms

Delta from earlier docs:

1. `priorities.user_id` → `dim_priority.owner_user_id`. NULL stays NULL.
2. `asset_categories.user_id` → `dim_asset_category.owner_user_id`. NULL stays NULL.
3. `asset_categories.service_type` → `asset_service_type_enum`. Empty / unknown → NULL.
4. `tenant_form_enabled` tinyint → boolean.
5. `is_deleted='yes'` → TRUE; filter out these rows at load.
6. On deletion, reconcile the dashboard tag clouds will shrink automatically because the dims are re-read.

Upsert on PK; no SCD here (cheap to overwrite).

---

## 6. Incremental load

### 6.1 Cursors
```
priorities.modified_at        > :c_priorities     - 10 min
asset_categories.modified_at  > :c_asset_cats     - 10 min
```

### 6.2 Upsert
```sql
INSERT INTO marts.dim_priority (priority_id, owner_user_id, priority_level,
        service_window, service_window_type, response_time, response_time_type,
        is_deleted, created_at, source_updated_at, loaded_at)
VALUES (...)
ON CONFLICT (priority_id) DO UPDATE SET
    owner_user_id       = EXCLUDED.owner_user_id,
    priority_level      = EXCLUDED.priority_level,
    service_window      = EXCLUDED.service_window,
    service_window_type = EXCLUDED.service_window_type,
    response_time       = EXCLUDED.response_time,
    response_time_type  = EXCLUDED.response_time_type,
    is_deleted          = EXCLUDED.is_deleted,
    source_updated_at   = EXCLUDED.source_updated_at,
    loaded_at           = now();
```
Same pattern for `dim_asset_category`.

### 6.3 Hard delete
`DELETE FROM marts.dim_priority WHERE priority_id = ANY($1)` — cascaded from `deleted_ids`.
Same for `dim_asset_category`, but note that `bridge_contract_asset_category`, `bridge_user_asset_category`, and `fact_work_order.asset_category_id` reference it. FKs are set with `ON DELETE SET NULL` on the facts / `CASCADE` on the bridges (established in earlier docs); the delete cascades correctly without extra work.

---

## 7. Materialized view — landing page

```sql
CREATE MATERIALIZED VIEW reports.mv_project_landing AS
WITH project_users AS (
    SELECT project_id, user_id FROM marts.bridge_user_project
),
-- Overview cards
asset_count AS (
    SELECT pu.project_id, COUNT(*)::BIGINT AS total_assets
    FROM project_users pu
    JOIN marts.fact_asset a ON a.owner_user_id = pu.user_id
    GROUP BY pu.project_id
),
wo_count AS (
    SELECT pu.project_id, COUNT(*)::BIGINT AS total_work_orders
    FROM project_users pu
    JOIN marts.fact_work_order wo ON wo.project_user_id = pu.user_id
    GROUP BY pu.project_id
),
-- Financial cards from commercial_contracts
fin AS (
    SELECT project_id,
           COUNT(*)                                         AS contract_count,
           COALESCE(SUM(amount),0)                          AS total_budget,
           COALESCE(SUM(security_deposit_amount),0)         AS security_deposit,
           COALESCE(SUM(payment_due),0)                     AS payment_due,
           COALESCE(SUM(payment_overdue),0)                 AS payment_overdue,
           COALESCE(SUM(late_fees_charge),0)                AS late_fees,
           COALESCE(SUM(brokerage_fee),0)                   AS brokerage,
           COALESCE(SUM(retainer_fee),0)                    AS retainer
    FROM marts.fact_commercial_contract
    WHERE NOT is_deleted
    GROUP BY project_id
),
-- Approved payrolls per project (joined through execution contracts)
payroll AS (
    SELECT pu.project_id, COUNT(*) AS approved_payrolls
    FROM project_users pu
    JOIN marts.dim_contract dc ON dc.owner_user_id = pu.user_id AND dc.is_current AND NOT dc.is_deleted
    JOIN marts.fact_contract_payroll pr ON pr.contract_id = dc.contract_id
    WHERE pr.file_status = 'Approved'
    GROUP BY pu.project_id
)
SELECT
    p.project_id,
    p.project_name,
    p.industry_type,

    COALESCE(ac.total_assets,      0) AS total_assets,
    COALESCE(wc.total_work_orders, 0) AS total_work_orders,
    COALESCE(f.total_budget,       0) AS total_budget,
    COALESCE(f.contract_count,     0) AS contract_count,

    COALESCE(f.security_deposit,   0) AS security_deposit,
    COALESCE(f.payment_due,        0) AS payment_due,
    COALESCE(f.payment_overdue,    0) AS payment_overdue,
    COALESCE(f.late_fees,          0) AS late_fees,
    COALESCE(f.brokerage,          0) AS brokerage,
    COALESCE(f.retainer,           0) AS retainer,
    COALESCE(pr.approved_payrolls, 0) AS approved_payrolls
FROM marts.dim_project p
LEFT JOIN asset_count  ac  ON ac.project_id  = p.project_id
LEFT JOIN wo_count     wc  ON wc.project_id  = p.project_id
LEFT JOIN fin          f   ON f.project_id   = p.project_id
LEFT JOIN payroll      pr  ON pr.project_id  = p.project_id
WHERE NOT p.is_deleted;

CREATE UNIQUE INDEX ix_mv_proj_landing ON reports.mv_project_landing(project_id);
```

### 7.1 Asset-category and priority tag clouds

The tag clouds read the dims directly (no mv needed):

```sql
-- asset categories for :project_id
SELECT DISTINCT ac.asset_category_id, ac.asset_category, ac.service_type, ac.status
FROM marts.dim_asset_category ac
JOIN marts.bridge_user_project up ON up.user_id = ac.owner_user_id
WHERE up.project_id = :project_id
  AND NOT ac.is_deleted
ORDER BY ac.asset_category;

-- priorities for :project_id
SELECT DISTINCT pr.priority_id, pr.priority_level, pr.service_window, pr.service_window_type,
                pr.response_time, pr.response_time_type
FROM marts.dim_priority pr
JOIN marts.bridge_user_project up ON up.user_id = pr.owner_user_id
WHERE up.project_id = :project_id
  AND NOT pr.is_deleted
ORDER BY pr.priority_level;
```

Both queries are index-backed (`ix_dim_asset_cat_owner`, `ix_dim_priority_owner`, `bridge_user_project` PK).

### 7.2 Project users table

```sql
SELECT u.user_id, u.full_name, u.email, u.user_type, u.user_type_label,
       u.phone, u.status, u.last_login_at
FROM marts.dim_user u
JOIN marts.bridge_user_project up ON up.user_id = u.user_id
WHERE up.project_id = :project_id
  AND NOT u.is_deleted
ORDER BY u.full_name;
```

---

## 8. API contract

Only two new endpoints — for the tables whose ingest shape changes to carry the extra columns. Otherwise the current ingest pipeline is used unchanged.

### 8.1 Priorities row

```json
{
  "id": 14,
  "user_id": 45,
  "priority_level": "High",
  "service_window": 4,
  "response_time": 1,
  "service_window_type": "hours",
  "response_time_type": "hours",
  "is_deleted": "no",
  "created_at": "2024-06-01T09:00:00Z",
  "modified_at": "2026-04-01T09:00:00Z"
}
```

### 8.2 Asset-category row

```json
{
  "id": 7,
  "user_id": 45,
  "asset_category": "HVAC",
  "service_type": "hard",
  "priority_id": 14,
  "tenant_form_enabled": 0,
  "status": 1,
  "is_deleted": "no",
  "created_at": "2024-06-01T09:00:00Z",
  "modified_at": "2026-04-01T09:00:00Z"
}
```

Endpoints:

```
POST /api/dwh/ingest/priorities       -- already listed in earlier docs; payload now extended
POST /api/dwh/ingest/asset-categories -- same
```

No dedicated endpoint is needed for `/select-project` or `/project-dashboard` landing — they read the warehouse, they don't write to it.

---

## 9. Validation checks

```yaml
models:
  - name: dim_priority
    columns:
      - name: owner_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }
      - name: priority_level
        tests: [not_null]

  - name: dim_asset_category
    columns:
      - name: owner_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }
      - name: default_priority_id
        tests:
          - relationships: { to: ref('dim_priority'), field: priority_id, severity: warn }
      - name: service_type
        tests:
          - accepted_values: { values: ['hard', 'soft'] }
```

Operational alerts:
- Tag cloud empty for a project that has assets → either `owner_user_id` is NULL or `bridge_user_project` is stale.
- `mv_project_landing` refresh age > 45 min → alert.
- Priorities count per project > 50 → likely junk data (source allows duplicates).

---

## 10. Open questions

1. Some `priorities.user_id` rows are NULL — they represent system defaults. Should the tag cloud show them as "Global" priorities on every project, or hide them? Current SQL hides them; confirm.
2. Should `dim_asset_category` dedupe on `(owner_user_id, asset_category)` (name-level) or stay at `id` level? Source has duplicates with the same name per user. Current: stay at id level; dashboard deduplicates by name on render.
3. `mv_project_landing.approved_payrolls` counts payrolls against execution contracts only. Is that the intended scope, or should lease-side commercial-contract payrolls count too? (Source doesn't have those; safe to leave as-is.)
4. `/select-project` access-control rule — confirm the list above (`super_admin`, `osool_admin`, `admin`, `admin_employee`) sees everything, everyone else is scoped. This needs to match Laravel app behavior today.
5. Should the financial overview cards on the landing page also include execution contracts (`dim_contract.contract_value`) not just `commercial_contracts.amount`? Currently no — lease money only. The per-project Contracts tab (doc #6) covers execution.

---

## File location

Saved as `docs/dwh/08-project-landing-and-select-project.md`.

---

## Final coverage summary

| # | Dashboard | Doc |
|---|---|---|
| 1 | Work Orders (global + per-project) | 01 |
| 2 | Properties (global + per-project) | 02 |
| 3 | Assets (per-project) | 03 |
| 4 | Users (per-project) + SSO auth schema | 04 |
| 5 | Billing / Lease (global + per-project) | 05 |
| 6 | Contracts (global + per-project + detail) | 06 |
| 7 | Overview | 07 |
| 8 | Select Project + Project Landing | 08 |

All dashboards in the current Laravel app are now covered. Every migration, every ingest endpoint, every materialized view the DWH needs is specified.

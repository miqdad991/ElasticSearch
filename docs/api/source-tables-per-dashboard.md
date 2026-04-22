# Source Tables & Relations per Dashboard

**Audience:** team building the source-side APIs that will push data to the DWH.
**Purpose:** one-page reference per dashboard listing every MySQL table touched, every join used, every filter predicate applied. Use this alongside `docs/dwh/01..08` to understand which tables each ingest endpoint must expose.

**Conventions in this doc:**
- `T.col` — column `col` on table `T`.
- `FK: A.x → B.y` — column `A.x` is a foreign key to `B.y`.
- All sources assume `is_deleted = 'no'` unless noted.
- `project_user_ids` means: `SELECT user_id FROM user_projects WHERE project_id = :pid`.

---

## 1. Work Orders

Covers `/work-orders` (global) and `/project-dashboard/workorders`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `work_orders` | primary grain | PK `id`, business key `work_order_id` (UNIQUE) |
| `asset_categories` | service category label | PK `id` |
| `asset_names` | asset name label | PK `id` |
| `priorities` | priority label + SLA | PK `id` |
| `property_buildings` | building label | PK `id` |
| `service_providers` | SP label | PK `id` |
| `contracts` | contract reference (optional) | PK `id` |
| `user_projects` | project scoping (per-project view only) | `(project_id, user_id)` |

### Joins

```sql
work_orders wo
  LEFT JOIN asset_categories   ac ON ac.id = wo.service_category_id   -- also wo.asset_category_id in places
  LEFT JOIN asset_names        an ON an.id = wo.asset_name_id
  LEFT JOIN priorities          p ON p.id  = wo.priority_id
  LEFT JOIN property_buildings pb ON pb.id = wo.property_id           -- NOTE: wo.property_id points at property_buildings.id
  LEFT JOIN service_providers  sp ON sp.id = wo.service_provider_id
  LEFT JOIN contracts           c ON c.id  = wo.contract_id
  -- per-project scoping:
  -- WHERE wo.project_user_id IN :project_user_ids
```

### Filter predicates

`wo.is_deleted = 'no'`, plus optional `service_type`, `work_order_type`, `contract_type`, `workorder_journey`, `status`, `priority_id`, `property_id`, `asset_category_id`, `asset_name_id`, date range on `wo.created_at`.

### Columns actually read

`wo.id, work_order_id, project_user_id, service_provider_id, property_id, asset_category_id, asset_name_id, priority_id, contract_id, contract_type, work_order_type, service_type, workorder_journey, status, maintanance_request_id (typo), cost, score, pass_fail, sla_response_time, response_time_type, sla_service_window, service_window_type, start_date, end_date, target_date, job_started_at, job_submitted_at, job_completion_date, created_at, modified_at` — plus the label columns from the joined tables.

---

## 2. Properties (per-project + global)

Covers `/project-dashboard/properties` and `/properties-dashboard`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `properties` | primary grain | PK `id` |
| `regions` | region lookup | PK `id` |
| `cities` | city lookup | PK `id`, FK `region_id` |
| `property_buildings` | building count / names | PK `id`, FK `property_id` |
| `commercial_contracts` | contracts-per-property, budget | see dashboard #5 |
| `assets` | total assets card | see #3 |
| `work_orders` | total work orders card | see #1 |
| `user_projects` | project scoping | `(project_id, user_id)` |

### Joins

```sql
properties p
  LEFT JOIN regions                r  ON r.id  = p.region_id
  LEFT JOIN cities                 c  ON c.id  = p.city_id
  LEFT JOIN property_buildings    pb  ON pb.property_id = p.id     -- 1:N
  -- Contract rollups:
  LEFT JOIN commercial_contracts  cc  ON cc.property_id = p.id     -- 1:N
  -- Per-project scope:
  -- WHERE p.user_id IN :project_user_ids
```

### Filters

`p.is_deleted = 'no' AND p.deleted_at IS NULL`, plus optional `status`, `property_type`, `location_type`, `region_id`, `city_id`, date range on `p.created_at`.

### Columns actually read

`p.id, user_id, project (property_name), property_tag, property_type, location_type, region_id, city_id, district_name, street_name, postal_code, building_number, buildings_count, total_floors, units_per_floor, total_units, property_usage, latitude, longitude, established_date, awqaf_contains, worker_housing, status, created_at` + labels from joined tables.

---

## 3. Assets

Covers `/project-dashboard/assets`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `assets` | primary grain | PK `id` |
| `asset_categories` | category label | PK `id` |
| `asset_names` | asset-name label | PK `id` |
| `asset_statuses` | status label | PK `id` |
| `property_buildings` | building label | PK `id` |
| `properties` | property label | PK `id` |
| `user_projects` | project scoping | `(project_id, user_id)` |

### Joins

```sql
assets a
  LEFT JOIN asset_categories     ac  ON ac.id = a.asset_category_id
  LEFT JOIN asset_names          an  ON an.id = a.asset_name_id
  LEFT JOIN asset_statuses      ast  ON ast.id = CAST(a.asset_status AS UNSIGNED)
  LEFT JOIN property_buildings   pb  ON pb.id = a.building_id
  LEFT JOIN properties            p  ON p.id  = a.property_id
  -- WHERE a.user_id IN :project_user_ids
```

### Filters

`a.is_deleted = 'no' AND a.deleted_at IS NULL`, plus optional `asset_status`, `asset_category_id`, `building_id`, `asset_name_id`, date range on `a.created_at`.

### Quirk

`assets.asset_status` is `VARCHAR(20)` — sometimes a numeric FK into `asset_statuses.id`, sometimes free text. Cast-when-numeric is the rule (`REGEXP '^\\d+$'`).

---

## 4. Users

Covers `/project-dashboard/users`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `users` | primary grain | PK `id` |
| `user_type` | display label for `users.user_type` slug | PK `slug` |
| `user_projects` | project scoping | `(project_id, user_id)` |

### Joins

```sql
users u
  LEFT JOIN user_type ut ON ut.slug = u.user_type
  -- WHERE u.id IN :project_user_ids
```

### Filters

`u.is_deleted = 'no'`, plus optional `user_type`, `status`, `is_deleted`, date range on `u.created_at`.

### Columns actually read (analytics only — no credentials)

`u.id, email, name, first_name, last_name, phone, profile_img, emp_id, user_type, project_user_id, sp_admin_id, service_provider, country_id, city_id, status, is_deleted, deleted_at, created_at, modified_at, last_login_datetime, device_type, selected_app_langugage (typo), langForSms, approved_max_amount, salary, allow_akaunting, akaunting_vendor_id, akaunting_customer_id, created_by` — plus the 8 CSV columns (`building_ids`, `contract_ids`, `role_regions`, `role_cities`, `asset_categories`, `keeper_warehouses`, `properties`, `beneficiary`) pre-split into arrays.

**Not loaded:** `password, temp_password, otp, otp_for_password, forgot_password_time, api_token, crm_api_token, device_token`. Auth is SSO.

---

## 5. Billing & Receivables (Lease / Tenant)

Covers `/billing-dashboard` and `/project-dashboard/billing`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `commercial_contracts` | contract grain | PK `id`, FK `project_id`, `tenant_id`, `property_id`, `building_id` |
| `payment_details` | installment grain | PK `id`, FK `contract_id` |
| `lease_contract_details` | 1:1 lease enrichment | PK `id`, FK `commercial_contract_id` |
| `projects_details` | project filter options | PK `id` |
| `users` | tenant lookup | PK `id` |

### Joins

```sql
payment_details pd
  LEFT JOIN commercial_contracts cc ON cc.id = pd.contract_id
  LEFT JOIN lease_contract_details lcd ON lcd.commercial_contract_id = cc.id  -- 1:1
  LEFT JOIN users u_tenant ON u_tenant.id = pd.tenant_id
  -- Per-project scope via cc.project_id = :pid
```

### Filters

`cc.is_deleted = 'no' AND cc.deleted_at IS NULL`, plus optional `contract_type`, `ejar_sync_status`, `project_id`, date range on `cc.created_at`. `pd.deleted_at IS NULL` always.

### Key derived metrics

- Outstanding: `payment_details.is_paid = 0`.
- Overdue: `is_paid = 0 AND payment_due_date < CURDATE()`.
- Aging: `DATEDIFF(CURDATE(), payment_due_date)` bucketed `0–30, 31–60, 61–90, 90+`.

---

## 6. Contracts (Execution) & Payment Tracking

Covers `/contracts-dashboard`, `/project-dashboard/contracts`, `/contracts-dashboard/{id}`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `contracts` | contract grain | PK `id`, self-FK `parent_contract_id`, FK `user_id`, `service_provider_id`, `contract_type_id` |
| `contract_types` | type label | PK `id` |
| `service_providers` | SP label | PK `id` |
| `contract_months` | monthly schedule | PK `id`, FK `contract_id` |
| `contract_priorities` | priority SLA | PK `id`, FK `contract_id`, `priority_id` |
| `contract_asset_categories` | category + default priority | FK `contract_id`, `asset_category_id`, `priority_id` |
| `contract_property_buildings` | properties covered | FK `contract_id`, `property_building_id` |
| `contract_usable_items` | Akaunting item links | FK `contract_id`, `item_id` |
| `contract_payrolls` | payroll workflow | PK `id`, FK `contract_id` |
| `contract_payroll_rejections` | rejection history | FK `payroll_id` |
| `contract_payroll_types` | payroll type label | PK `id` |
| `contract_documents` | documents tab | FK `contract_id` |
| `contract_inspection_reports` | inspections tab | FK `contract_id` |
| `contract_performance_indicators` | KPI definitions | FK `contract_id` |
| `contract_service_kpi` | per-service KPI + price | FK `contract_id` |
| `work_orders` | WO extras (cost where `status=4`) | FK `contract_id` |
| `mapping_osool_akaunting` | bill-id bridge | `(osool_document_id, document_type)` |
| `user_projects` | per-project scoping | `(project_id, user_id)` |

### Joins

```sql
contracts c
  LEFT JOIN contract_types      ct ON ct.id = c.contract_type_id
  LEFT JOIN service_providers   sp ON sp.id = c.service_provider_id
  LEFT JOIN contract_months    cm ON cm.contract_id = c.id                   -- schedule
  LEFT JOIN contract_priorities cpr ON cpr.contract_id = c.id
  LEFT JOIN contract_asset_categories cac ON cac.contract_id = c.id
  LEFT JOIN work_orders wo ON wo.contract_id = c.id AND wo.status = 4        -- WO extras
  -- Scoping:
  --   WHERE c.is_deleted = 'no' AND c.contract_type_id NOT IN (6,7)
  --   AND c.user_id IN :project_user_ids  (per-project)
  --   AND c.id = :contract_id            (detail)
```

### CSV columns to expose as pre-parsed arrays

`contracts.region_id`, `contracts.city_id`, `contracts.asset_categories`, `contracts.asset_names` — source stores as CSV; API must deliver as JSON array.

### Key derived metrics

- Overdue months: `contract_months.is_paid = 0 AND STR_TO_DATE(month,'%Y-%m-%d') < CURDATE()`.
- Timeline %: `(today - start_date) / (end_date - start_date) * 100`, clamped.
- Closed WO extras: `work_orders.status = 4 AND contract_id IN scope`, `SUM(cost)`.
- Subcontract flag: `contracts.parent_contract_id IS NOT NULL`.

---

## 7. Overview

Covers `/overview`.

### Tables

| Table | Role | Keys |
|---|---|---|
| `projects_details` | project grain | PK `id` |
| `users` | admin counts, project owner | PK `id` |
| `properties` | property rollup | PK `id` |
| `service_providers` | SP count | PK `id` |
| `service_providers_project_mapping` | per-project SP count | `(service_provider_id, project_id)` |
| `packages` | subscription catalog | PK `id` |
| `user_projects` | project membership | `(project_id, user_id)` |
| `contracts` | per-project execution value | FK `user_id` |
| `commercial_contracts` | per-project lease due/overdue | FK `project_id` |

### Joins

```sql
projects_details pd
  LEFT JOIN users u                      ON u.id = pd.user_id
  LEFT JOIN user_projects up             ON up.project_id = pd.id
  LEFT JOIN properties p                 ON p.user_id = up.user_id AND p.is_deleted = 'no'
  LEFT JOIN service_providers_project_mapping spm ON spm.project_id = pd.id
  LEFT JOIN contracts c                  ON c.user_id = up.user_id AND c.deleted_at IS NULL
  LEFT JOIN commercial_contracts cc      ON cc.project_id = pd.id AND cc.is_deleted = 'no'
```

### Filters

`packages.deleted_at IS NULL`. Soft filter on each subquery's own `is_deleted` / `deleted_at`.

### Key derived metrics

- `packages.effective_price = price - (price * discount / 100)` when discount > 0 else `price`.
- `projects_details.is_deleted` uses **0/1** — not `yes/no`. API must keep the original shape.

---

## 8. Project Selector + Project Landing

Covers `/select-project` and `/project-dashboard` (landing, not tabs).

### Tables

| Table | Role |
|---|---|
| `projects_details` | project list |
| `user_projects` | membership for access control |
| `users` | project users table |
| `user_type` | type label |
| `asset_categories` | tag cloud — **needs `user_id`** |
| `priorities` | tag cloud — **needs `user_id`** |
| `commercial_contracts` | financial cards on landing |
| `assets`, `work_orders` | top cards |
| `contract_payrolls`, `contracts` | approved-payroll count |

### Joins

```sql
-- asset categories tag cloud
SELECT DISTINCT ac.id, ac.asset_category, ac.service_type, ac.status
FROM asset_categories ac
WHERE ac.user_id IN :project_user_ids AND ac.is_deleted = 'no';

-- priorities tag cloud
SELECT DISTINCT p.id, p.priority_level, p.service_window, p.service_window_type,
                p.response_time, p.response_time_type
FROM priorities p
WHERE p.user_id IN :project_user_ids AND p.is_deleted = 'no';

-- landing cards
SELECT
  (SELECT COUNT(*) FROM assets       WHERE user_id IN :pu AND is_deleted='no' AND deleted_at IS NULL) AS total_assets,
  (SELECT COUNT(*) FROM work_orders  WHERE project_user_id IN :pu AND is_deleted='no')                AS total_work_orders,
  (SELECT COALESCE(SUM(amount),0) FROM commercial_contracts WHERE project_id = :pid AND is_deleted='no') AS total_budget,
  (SELECT COUNT(*) FROM commercial_contracts WHERE project_id = :pid AND is_deleted='no')             AS contracts_total;
```

### Filters

All scoped by `user_projects.project_id = :pid → user_ids` or directly by `commercial_contracts.project_id`.

### Important delta

`priorities.user_id` and `asset_categories.user_id` were easy to miss — both are **required** in the API payload for this page's tag clouds.

---

## Master table inventory

Every table touched by the dashboards, consolidated. Each row says which dashboard(s) use it and whether the API must expose a full read or can stay read-only lookups.

| Table | Used by dashboards | API shape |
|---|---|---|
| `work_orders` | 1 | full incremental + delete list |
| `assets` | 3 | full incremental + delete list |
| `properties` | 2 | full incremental + delete list |
| `property_buildings` | 1, 2, 3, 6 | full incremental + delete list |
| `users` | 4 (+ every dashboard indirectly) | full incremental + delete list (no secrets) |
| `user_type` | 4 | full snapshot (tiny) |
| `user_projects` | 1, 3, 4, 5, 6, 7, 8 | full snapshot per cycle |
| `projects_details` | 7, 8 | full incremental + delete list |
| `asset_categories` | 1, 3, 6, 8 | full incremental + delete list — must include `user_id` |
| `asset_names` | 1, 3, 6 | full snapshot |
| `priorities` | 1, 6, 8 | full incremental + delete list — must include `user_id` |
| `asset_statuses` | 3 | full snapshot |
| `regions` | 2 | full snapshot |
| `cities` | 2 | full snapshot |
| `service_providers` | 1, 6, 7 | full incremental + delete list |
| `service_providers_project_mapping` | 7 | full snapshot per cycle |
| `contracts` | 1 (FK only), 6 | full incremental + delete list |
| `contract_types` | 6 | full snapshot |
| `contract_months` | 6 | full incremental + delete list |
| `contract_priorities` | 6 | full incremental + delete list |
| `contract_asset_categories` | 6 | full incremental (replace-per-contract) |
| `contract_property_buildings` | 6 | full incremental (replace-per-contract) |
| `contract_usable_items` | 6 | full incremental (replace-per-contract) |
| `contract_payrolls` | 6, 8 | full incremental + delete list |
| `contract_payroll_rejections` | 6 | full incremental + delete list |
| `contract_payroll_types` | 6 | full snapshot |
| `contract_documents` | 6 | full incremental + delete list |
| `contract_inspection_reports` | 6 | full incremental + delete list |
| `contract_performance_indicators` | 6 | full incremental + delete list |
| `contract_service_kpi` | 6 | full incremental + delete list |
| `commercial_contracts` | 2, 5, 7, 8 | full incremental + delete list |
| `payment_details` | 5 | full incremental + delete list |
| `lease_contract_details` | 5 | full incremental + delete list |
| `packages` | 7 | full incremental + delete list |
| `mapping_osool_akaunting` | 6 | full incremental + delete list |

**"Full snapshot per cycle"** — no `modified_at`, small volume; API sends the complete current state each time, DWH diffs.
**"Replace-per-contract"** — the junction rewrites every row for a contract when that contract changes. API accepts `{contract_id, rows: [...]}` and DWH deletes-then-inserts in one transaction.

---

## Cross-cutting rules for the API

1. **Per-table endpoint.** One `POST /api/dwh/ingest/<resource>` per table above.
2. **Envelope** (same as doc #1 §8.1):
   ```json
   {
     "cursor_from": "...", "cursor_to": "...",
     "rows": [ ... ],
     "deleted_ids": [ ... ]
   }
   ```
3. **Idempotency.** Every call carries `X-Idempotency-Key`; retries must be safe.
4. **Row shape = source column names**, verbatim, including known typos (`maintanance_request_id`, `calender_type`, `selected_app_langugage`, `langForSms`) — rename happens in the DWH ETL, not in transit.
5. **Pre-parsed arrays** for CSV columns: `contracts.region_id`, `contracts.city_id`, `contracts.asset_categories`, `contracts.asset_names`, and all 8 CSV columns on `users`.
6. **Enums stay enums** (strings). `is_deleted` stays `'yes'`/`'no'` on most tables, `0`/`1` on `projects_details` — don't change either.
7. **No secrets.** `users.password`, `temp_password`, OTP / API-token columns, bank accounts, IBANs — never in a payload. Bank / IBAN tokenization happens in the DWH (doc #5 §A).
8. **Cursor** = `max(modified_at)` of the rows in the batch. The DWH stores per-table cursor.
9. **Ordering per cycle** (dependencies):
   - Stage 1 (no deps): `user_type`, `contract_types`, `contract_payroll_types`, `asset_names`, `asset_statuses`, `regions`, `packages`.
   - Stage 2: `cities`, `service_providers`, `users` (dim), `projects_details`.
   - Stage 3: `user_projects`, `service_providers_project_mapping`, `properties`.
   - Stage 4: `property_buildings`, `asset_categories`, `priorities`.
   - Stage 5: `commercial_contracts`, `contracts`, `assets`.
   - Stage 6: everything else (facts, junctions, payrolls, documents, KPIs, `work_orders`, `payment_details`, `lease_contract_details`, `contract_months`, `mapping_osool_akaunting`).

---

## File location

Saved at `docs/api/source-tables-per-dashboard.md`. Pair this with `docs/dwh/01..08` when building the source-side API.

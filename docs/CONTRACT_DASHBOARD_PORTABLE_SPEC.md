# Contract Detail Dashboard — Portable Specification

**Purpose:** Rebuild the Osool-B2G contract detail page (`/data/contracts/{id}`) in another project that shares the **same MySQL database** but does **not have the original Laravel code**.

**Audience:** Backend/frontend engineers on a sibling project.

**Generated:** 2026-04-12

This document describes only what a consumer needs: the DB tables involved, the SQL to read them, the formulas to compute derived values, and the page layout.

---

## 1. Page Layout Overview

The dashboard is a single contract "show" page organized into these sections (top to bottom):

1. **Header** — contract number, edit/delete actions, breadcrumb
2. **Timeline / Progress** — start_date → end_date with % completion and overall KPI
3. **Contract Info Card** — ID (formatted `CONT####`), status, contract number, service provider, service types (up to 5 with "view all" modal)
4. **Covered Area & Assets** — regions, cities, properties, asset names, warehouse ownership, spare parts flag
5. **Additional Settings (collapsed)** — assigned supervisors, unit receival form, allow subcontract, smart assign
6. **Workforce Allocation** — engineers / administrators / supervisors / workers counts
7. **Notes & Files** — comment + attached file
8. **Service Types & Priorities** (tabs) — asset categories with priority levels; priority SLA table (service window + response time)
9. **Subcontracts** (conditional) — child contracts table
10. **Contract Items** — items from external Akaunting inventory
11. **Payment & Bill Tracking** — summary cards (Pending / Paid This Year / Overdue) + monthly payment history table
12. **Tabs** — Payrolls, Documents, Inspection Reports (each a DataTable)
13. **Detail modals** — WO list, spare parts, full service list, etc.

---

## 2. URL & Identifier

The original project encrypts the contract id in the URL (`Laravel Crypt::encryptString`). In a new project you will pass the raw integer id. All queries below assume `:contract_id` = integer PK of `contracts`.

Contract types **6 and 7** are "advance contracts" (`ADVANCECONTRACTS`, `ADVANCESUBCONTRACTS`) — they use a different detail page. Filter them out here:

```sql
SELECT contract_type_id FROM contracts WHERE id = :contract_id;
-- If result is 6 or 7, redirect to the advance-contract view instead.
```

---

## 3. Core Tables & Keys

### 3.1 `contracts` (main row)

Key columns needed by the page:

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `contract_number` | VARCHAR | Display as `CONT` + zero-padded id |
| `service_provider_id` | INT FK → `service_providers.id` | |
| `contract_type_id` | BIGINT FK → `contract_types.id` | 6/7 = advance contracts |
| `start_date`, `end_date` | DATE | Timeline card |
| `region_id`, `city_id` | VARCHAR 255 | **CSV of IDs** |
| `asset_categories`, `asset_names` | VARCHAR 3500 | **CSV of IDs** |
| `properties` | VARCHAR 25 | legacy; use junction table instead |
| `workers_count`, `supervisor_count`, `administrator_count`, `engineer_count` | INT | workforce card |
| `contract_value` | FLOAT | used in payment math |
| `retention_percent`, `discount_percent` | DECIMAL(5,2) | |
| `spare_parts_included` | ENUM('yes','no') | |
| `allow_subcontract` | TINYINT | |
| `parent_contract_id` | BIGINT | **self-FK — subcontracts** |
| `warehouse_id`, `warehouse_owner` | — | |
| `comment`, `file_path` | text / path | Notes & Files |
| `status` | TINYINT | 1 = active |
| `is_deleted` | ENUM('yes','no') | filter `= 'no'` |

### 3.2 Supporting tables

| Table | Purpose | Join column |
|---|---|---|
| `service_providers` | Company info, name, status | `contracts.service_provider_id → id` |
| `contract_asset_categories` | Services the contract covers (with priority) | `contract_id` |
| `asset_categories` | Lookup for service type names | `id` |
| `contract_property_buildings` | Junction — properties covered | `contract_id` + `property_building_id` |
| `property_buildings` | Building/property info | `id` |
| `contract_priorities` | Priority-level SLA (window, response time) | `contract_id` |
| `contract_months` | Monthly payment schedule — drives payment dashboard | `contract_id`, `month`, `amount`, `is_paid`, `status`, `bill_id` |
| `contract_usable_items` | Items linked from Akaunting inventory | `contract_id`, `item_id` |
| `contract_performance_indicators` | KPI definitions | `contract_id` |
| `contract_service_kpi` | KPI with price + weight (JSON `performance_indicator`) | `contract_id`, `service_id` |
| `contract_payrolls` | Payroll tab | `contract_id`, `file_status` ENUM |
| `contract_documents` | Documents tab | `contract_id`, `file_status` ENUM |
| `contract_inspection_reports` | Inspection tab (JSON `file_paths`) | `contract_id`, `file_status` ENUM |
| `work_orders` | Operational WOs under the contract | `contract_id` |
| `work_order_states` | Current status of each WO (`status = 4` = Closed) | `work_order_id` |
| `work_order_schedules` | Has `job_completion_date` used for period filtering | `work_order_id` |
| `work_order_costs` | `cost` column = "extra amount" added to a period | `work_order_id` |
| `worker_spare_parts_requests` | Spare parts count per WO | `work_order_id` |
| `worker_spare_parts_requested_items` | Line items | `request_id` |
| `mapping_osool_akaunting` | Maps `osool_document_id → akaunting_document_id` by `document_type` | — |
| `cities`, `regions` | Lookup for `contracts.city_id` / `region_id` CSV explode | `id IN (...)` |

### 3.3 Constants

```
work_order_states.status:
  1 = open, 2 = in progress, 3 = on hold,
  4 = CLOSED          ← used for payment period calculation
  5 = deleted, 6 = reopened, 7 = warranty, 8 = scheduled

contract_types.id:
  1 = REGULAR, 2 = MAINTENANCE, 3 = WARRANTY, 4 = SERVICE, 5 = SUBCONTRACT,
  6 = ADVANCECONTRACTS, 7 = ADVANCESUBCONTRACTS
```

---

## 4. Reading the Header / General Info

```sql
SELECT
  c.id, c.contract_number, c.start_date, c.end_date, c.status,
  c.contract_value, c.retention_percent, c.discount_percent,
  c.spare_parts_included, c.allow_subcontract,
  c.workers_count, c.supervisor_count, c.administrator_count, c.engineer_count,
  c.comment, c.file_path, c.parent_contract_id,
  c.region_id, c.city_id, c.asset_categories, c.asset_names,
  c.warehouse_id, c.warehouse_owner,
  sp.id   AS sp_id,
  sp.name AS sp_name,
  sp.deleted_at AS sp_deleted_at
FROM contracts c
JOIN service_providers sp ON sp.id = c.service_provider_id
WHERE c.id = :contract_id
  AND c.is_deleted = 'no';
```

Parse CSV columns on the client:
- `c.region_id` → `explode(",")` → `SELECT id, name FROM regions WHERE id IN (...)`
- `c.city_id` → same with `cities`
- `c.asset_categories` → same with `asset_categories`
- `c.asset_names` → same with `asset_names`

---

## 5. Section Queries

### 5.1 Covered properties
```sql
SELECT pb.*
FROM contract_property_buildings cpb
JOIN property_buildings pb ON pb.id = cpb.property_building_id
WHERE cpb.contract_id = :contract_id;
```

### 5.2 Service types & priorities (tabs)
```sql
-- services with their priority id
SELECT cac.asset_category_id, ac.name, cac.priority_id
FROM contract_asset_categories cac
JOIN asset_categories ac ON ac.id = cac.asset_category_id
WHERE cac.contract_id = :contract_id;

-- SLA per priority
SELECT priority_id, service_window, service_window_type,
       response_time, response_time_type
FROM contract_priorities
WHERE contract_id = :contract_id;
```

### 5.3 Subcontracts
```sql
SELECT c.id, c.contract_number, sp.name AS sp_name
FROM contracts c
JOIN service_providers sp ON sp.id = c.service_provider_id
WHERE c.parent_contract_id = :contract_id
  AND c.is_deleted = 'no';
```

### 5.4 Contract items (from Akaunting)
```sql
SELECT item_id, company_id, warehouse_id
FROM contract_usable_items
WHERE contract_id = :contract_id;
-- Then fetch item details from the Akaunting items table / API.
```

### 5.5 KPIs
```sql
SELECT cpi.performance_indicator, cpi.range_id
FROM contract_performance_indicators cpi
WHERE cpi.contract_id = :contract_id;

SELECT cs.service_id, cs.performance_indicator AS kpi_json,
       cs.price, cs.description
FROM contract_service_kpi cs
WHERE cs.contract_id = :contract_id
  AND cs.deleted_at IS NULL;
```

### 5.6 Tab lists
```sql
-- Payrolls
SELECT * FROM contract_payrolls
WHERE contract_id = :contract_id
ORDER BY created_at DESC;

-- Documents
SELECT * FROM contract_documents
WHERE contract_id = :contract_id AND archived = 0
ORDER BY created_at DESC;

-- Inspection reports
SELECT * FROM contract_inspection_reports
WHERE contract_id = :contract_id AND archived = 0
ORDER BY created_at DESC;
```

`file_status` ENUM: `Pending | Review | Approved | Rejected` — render as badge.

---

## 6. Payment & Bill Tracking (core logic)

This is the most complex section; the original project computes it in `ContractPaymentService::getPaymentTrackingData`. Reproduce the algorithm as follows.

### 6.1 Load the schedule
```sql
SELECT id, contract_id, month, amount, is_paid, status, bill_id, created_at
FROM contract_months
WHERE contract_id = :contract_id AND deleted_at IS NULL
ORDER BY month ASC;
```

`month` is a date string (e.g. `"2025-04-01"`).

### 6.2 Compute period window for row *n*

```
end_date_n   = LAST_DAY(month_n)
start_date_n = (n == 0) ? first_day_of(month_n)
                        : end_date_(n-1) + 1 day      -- consecutive / gap-fill
```

### 6.3 Per-period WO extras + spare parts

```sql
SELECT
  COUNT(DISTINCT wo.id)                       AS wo_count,
  COALESCE(SUM(woc.cost), 0)                  AS extra_amount,
  COUNT(DISTINCT wsprr.id)                    AS spare_parts_count
FROM work_orders wo
JOIN work_order_states    wos     ON wos.work_order_id    = wo.id
JOIN work_order_schedules wosched ON wosched.work_order_id = wo.id
LEFT JOIN work_order_costs             woc   ON woc.work_order_id   = wo.id
LEFT JOIN worker_spare_parts_requests  wsprr ON wsprr.work_order_id = wo.id
WHERE wo.contract_id = :contract_id
  AND wos.status = 4                              -- Closed
  AND wosched.job_completion_date BETWEEN :period_start AND :period_end;
```

### 6.4 Performance deduction

For the period, look up its KPI score (0–100). Deduction follows this pattern in the source service:

```
kpi_deduction = monthly_amount × kpi_weight × (penalty_percent / 100)
```

`kpi_weight` and `penalty_percent` come from the `performance_indicator` JSON on `contract_service_kpi` and the computed score for that period (see `PerformanceIndicatorService`).

If your project does not require KPI math yet, start with `kpi_deduction = 0`.

### 6.5 Total per period

```
total = monthly_amount + extra_amount - kpi_deduction
```

### 6.6 Status derivation (rank from first match)

| Condition | Status |
|---|---|
| `is_paid = 1` OR `status = 'Paid'` | **Paid** |
| `status = 'Partially Paid'` | **Partially Paid** |
| `status = 'Overdue'` OR (`today > period_end` AND NOT paid) | **Overdue** |
| `period_start > today` | **Upcoming** |
| otherwise | **Pending** |

### 6.7 Summary cards

Aggregate over `table_data`:

- **Pending Payments** = count(Pending) + count(Upcoming) and sum of their totals
- **Paid This Year** = count(Paid) + count(Partially Paid) where period is in the current year
- **Overdue** = count(Overdue) and sum of their totals

### 6.8 Bill link

`contract_months.bill_id` points to an Akaunting invoice id. Build the URL via your Akaunting bridge, or look it up in:

```sql
SELECT akaunting_document_id
FROM mapping_osool_akaunting
WHERE osool_document_id = :contract_month_id
  AND document_type = 'invoice';
```

---

## 7. Overall KPI / Progress

- **Timeline %** = `(today − start_date) / (end_date − start_date) × 100` clamped to `[0, 100]`.
- **Overall performance %** = average across all `contract_performance_indicators.range_id` scores for this contract (normalized 0–100). Shown only when Akaunting is enabled.

---

## 8. Subcontract Mechanism

- Self-FK on `contracts.parent_contract_id`.
- A contract with non-null `parent_contract_id` is a subcontract.
- Aggregation is usually flat (parent's dashboard does not sum child amounts automatically).

---

## 9. JSON / Denormalized Columns (watch out!)

| Column | Format | Parsing |
|---|---|---|
| `contracts.region_id` | CSV of integers | `explode(",")` → `IN (...)` on `regions` |
| `contracts.city_id` | CSV of integers | `cities` |
| `contracts.asset_categories` | CSV of integers | `asset_categories` |
| `contracts.asset_names` | CSV of integers | `asset_names` |
| `contract_service_kpi.performance_indicator` | JSON object | KPI weights and thresholds |
| `contract_inspection_reports.file_paths` | JSON array | List of uploaded files |

---

## 10. Authorization Notes (port as-is)

Original page checks the signed-in user has `view` privilege on `contracts`. Sections are additionally gated by role:

- `admin` — full access
- `building_manager` — view + limited edit
- `sp_admin`, `supervisor` — restricted edit

Feature flags on the page:
- **Akaunting enabled** — show items & invoice links
- **Unit receival form** — show the form toggle
- **Smart assign** — show the toggle

---

## 11. Master Query (one-shot fetch)

For a fast paint, fire these in parallel:

```sql
-- 1) main row (see §4)
-- 2) properties (§5.1)
-- 3) services / priorities (§5.2)
-- 4) subcontracts (§5.3)
-- 5) items (§5.4)
-- 6) KPIs (§5.5)
-- 7) schedule (§6.1)
-- 8) payrolls / documents / inspection reports (§5.6 — lazy, via DataTable AJAX)
```

Then compute per-period numbers (§6.2–6.6) in a single grouped query:

```sql
SELECT
  cm.id, cm.month, cm.amount, cm.is_paid, cm.status, cm.bill_id,
  COALESCE(SUM(woc.cost), 0)       AS extra_amount,
  COUNT(DISTINCT wo.id)            AS wo_count,
  COUNT(DISTINCT wsprr.id)         AS spare_parts_count
FROM contract_months cm
LEFT JOIN work_orders wo
  ON wo.contract_id = cm.contract_id
LEFT JOIN work_order_states wos
  ON wos.work_order_id = wo.id AND wos.status = 4
LEFT JOIN work_order_schedules wosched
  ON wosched.work_order_id = wo.id
 AND wosched.job_completion_date BETWEEN DATE_FORMAT(STR_TO_DATE(cm.month,'%Y-%m-%d'),'%Y-%m-01')
                                     AND LAST_DAY(STR_TO_DATE(cm.month,'%Y-%m-%d'))
LEFT JOIN work_order_costs woc
  ON woc.work_order_id = wo.id
LEFT JOIN worker_spare_parts_requests wsprr
  ON wsprr.work_order_id = wo.id
WHERE cm.contract_id = :contract_id
  AND cm.deleted_at IS NULL
GROUP BY cm.id, cm.month, cm.amount, cm.is_paid, cm.status, cm.bill_id
ORDER BY cm.month ASC;
```

(Note: the period-window is approximated here as the month of `cm.month`; for *consecutive/gap-filling* logic described in §6.2, compute the windows in application code and re-query.)

---

## 12. Checklist for the Other Project

- [ ] Read-only DB user with SELECT on all tables in §3.
- [ ] Page scaffolding with 13 sections in the order of §1.
- [ ] Master fetch (§11) + KPI service.
- [ ] Period window computation (§6.2) in app code.
- [ ] Status derivation ranked per §6.6.
- [ ] Bill link resolver via `mapping_osool_akaunting`.
- [ ] CSV column parser for region / city / asset_categories / asset_names.
- [ ] DataTables (AJAX) for payrolls, documents, inspection reports.
- [ ] Status badges with the 4 ENUM values.
- [ ] Feature flags: Akaunting, unit receival, smart assign.
- [ ] Redirect logic when `contract_type_id IN (6, 7)`.

---

## 13. Glossary

| Term | Meaning |
|---|---|
| Closed WO | `work_order_states.status = 4` |
| Extra amount | Sum of `work_order_costs.cost` for closed WOs in a period |
| Spare parts count | Distinct `worker_spare_parts_requests.id` in period |
| KPI deduction | Penalty subtracted from monthly amount based on performance score |
| Advance contract | `contract_type_id` 6 or 7 — different UI |
| Subcontract | Contract with non-null `parent_contract_id` |
| Akaunting | External accounting system; invoices live there, bridged via `mapping_osool_akaunting` |

# Workorder Dashboard Analysis & Data Warehouse ETL Recommendation

**Date:** 2026-04-02  
**Project:** OpenSearch Dashboard (Laravel)  
**Route Analyzed:** `/project-dashboard/workorders?page=1`

---

## 1. Current Dashboard Data Structure

### Core Entity: `work_orders` Table

| Field | Type | Purpose |
|-------|------|---------|
| work_order_id | ID | Business identifier |
| work_order_type | enum | `preventive` / `reactive` |
| service_type | enum | `hard` / `soft` |
| contract_type | string | Contract classification |
| status | int | 1-8 (Open, In Progress, On Hold, Closed, Deleted, Re-open, Warranty, Scheduled) |
| property_id | FK | Links to `property_buildings` |
| priority_id | FK | Links to `priorities` |
| asset_category_id | FK | Links to `asset_categories` |
| service_provider_id | FK | Provider reference |
| project_user_id | FK | Links to `user_projects` |
| is_deleted | string | Soft delete flag (`yes`/`no`) |
| created_at / modified_at | datetime | Timestamps |

### Related Tables

- **`work_order_states`** — Journey/status tracking (workorder_journey, status, start_date, end_date, target_date)
- **`property_buildings`** — Building/property names
- **`priorities`** — Priority levels, SLA windows, response times
- **`asset_categories`** — Asset category names and service types
- **`user_projects`** — Maps users to projects

### Controllers Serving This Data

1. **`ProjectDashboardController@workorders`** (`/project-dashboard/workorders`)
   - Project-scoped via session
   - 8 filter dimensions
   - Returns: 5 stat cards, 5 charts (monthly, status, category, property, priority), paginated table (20/page)

2. **`WorkOrderReportController@data`** (`/api/work-orders`)
   - Joins work_orders with work_order_states
   - Returns JSON: paginated records (50/page), aggregations (service_type, journey, wo_type, status, monthly_trend)

### Dashboard Metrics Currently Computed

- Total work orders count
- Preventive vs Reactive breakdown
- Hard vs Soft service breakdown
- Monthly trends (DATE_FORMAT grouped)
- By asset category (top 15)
- By status (8 statuses)
- By property/building (top 15)
- By priority level

---

## 2. Current Pain Points

1. **Every dashboard load runs 7+ separate COUNT/GROUP BY queries** against the live OLTP database
2. **Aggregation queries are duplicated** between `ProjectDashboardController` and `WorkOrderReportController`
3. **No caching** — filters trigger full table scans each time
4. **The OpenSearch sync** (`SyncToOpenSearch` command) exists but isn't used by the dashboard controllers
5. **90k+ records** queried on every page load with no pre-computation

---

## 3. Recommended Architecture: Star Schema + Materialized Views

### Option A: Lightweight DWH Inside MySQL (Recommended to Start)

Build a star schema in a separate MySQL database/schema:

```
+-----------------------------------------------------+
|                  FACT TABLE                          |
|  fact_work_orders                                   |
|  -----------------                                  |
|  wo_key (surrogate PK)                              |
|  work_order_id (business key)                       |
|  dim_date_key -> dim_date                           |
|  dim_property_key -> dim_property                   |
|  dim_priority_key -> dim_priority                   |
|  dim_asset_category_key -> dim_asset_category       |
|  dim_service_provider_key -> dim_service_provider   |
|  dim_project_key -> dim_project                     |
|  dim_status_key -> dim_status                       |
|  work_order_type (degenerate dim)                   |
|  service_type (degenerate dim)                      |
|  contract_type (degenerate dim)                     |
|  --- MEASURES ---                                   |
|  time_to_close_hours                                |
|  time_in_status_hours                               |
|  sla_met (boolean)                                  |
|  response_time_hours                                |
|  created_at, modified_at                            |
+-----------------------------------------------------+

DIMENSION TABLES:

+----------------+  +----------------+  +--------------------+
| dim_date       |  | dim_property   |  | dim_asset_category |
| date_key       |  | property_key   |  | category_key       |
| full_date      |  | building_name  |  | asset_category     |
| year           |  | project_id     |  | service_type       |
| quarter        |  | ...            |  | ...                |
| month          |  +----------------+  +--------------------+
| month_name     |
| week           |  +----------------+  +--------------------+
| day_of_week    |  | dim_priority   |  | dim_status         |
| is_weekend     |  | priority_key   |  | status_key         |
+----------------+  | priority_lvl   |  | status_label       |
                    | sla_window     |  | journey_stage      |
                    | response_time  |  | ...                |
                    +----------------+  +--------------------+
```

### ETL Pipeline (Laravel-native)

```
[Source: osool_bef_normalization]
         |
         v
   +-------------+
   |  EXTRACT     |  Laravel Command: artisan etl:extract
   |  Raw tables  |  - Incremental by modified_at watermark
   |  -> staging  |  - Track last_extracted_at per table
   +------+------+
          |
          v
   +-------------+
   |  TRANSFORM   |  Laravel Command: artisan etl:transform
   |  Clean,      |  - Resolve FKs to surrogate keys
   |  Derive SLA  |  - Calculate time_to_close, sla_met
   |  metrics     |  - Handle slowly changing dims (SCD Type 2)
   +------+------+
          |
          v
   +-------------+
   |  LOAD        |  Laravel Command: artisan etl:load
   |  Upsert into |  - Fact + dimension tables
   |  star schema |  - Update pre-aggregated summary tables
   +------+------+
          |
          v
   +-------------+
   |  Optional:   |  artisan opensearch:sync-dwh
   |  Sync to     |  - Push fact table to OpenSearch
   |  OpenSearch   |  - Enables sub-second dashboard queries
   +-------------+
```

### Pre-Aggregated Summary Tables

These replace the 7+ live COUNT queries currently running on every dashboard load:

```sql
-- Refreshed by ETL, replaces live COUNT queries
summary_workorders_monthly     (project_id, year_month, wo_type, service_type, status, count, ...)
summary_workorders_by_property (project_id, property_id, status, count, ...)
summary_workorders_by_category (project_id, asset_category_id, count, ...)
summary_workorders_by_priority (project_id, priority_id, count, ...)
```

Dashboard controller becomes simple `SELECT * FROM summary_*` instead of 7 aggregation queries.

---

### Option B: OpenSearch as the DWH (If Real-Time Needed)

Enhance the existing OpenSearch sync:

1. **Denormalize at sync time** — flatten joins into the OpenSearch document (include building_name, priority_level, asset_category directly)
2. **Use OpenSearch aggregations** for all dashboard charts (built for this)
3. **Schedule incremental syncs** every 5-15 minutes via Laravel scheduler

Faster to implement but harder for complex SLA calculations.

---

## 4. Comparison

| Criteria | Option A (MySQL Star) | Option B (OpenSearch) |
|----------|----------------------|----------------------|
| Setup effort | Medium | Low |
| Query speed | Fast (pre-aggregated) | Very fast |
| SLA/KPI calculations | Easy (SQL) | Harder |
| Historical analysis | Excellent (SCD) | Limited |
| Infrastructure | No new infra | Already have it |
| Scalability at 90k rows | More than enough | Overkill |

**Recommendation:** For 90k records, a MySQL star schema with pre-aggregated summaries is the right level of complexity. OpenSearch is great for full-text search and real-time, but the use case here is structured analytics.

---

## 5. Implementation Steps

1. **Create a `dwh` database** (or schema) separate from the OLTP source
2. **Build dimension tables** first (dim_date, dim_property, dim_priority, dim_status, dim_asset_category)
3. **Build the fact table** with surrogate keys + calculated measures (SLA metrics, time-to-close)
4. **Build summary/aggregate tables** that mirror the current dashboard queries
5. **Write 3 artisan commands**: `etl:extract`, `etl:transform`, `etl:load`
6. **Schedule via Laravel Scheduler** (hourly or nightly depending on freshness needs)
7. **Refactor dashboard controllers** to read from summary tables

---

## 6. Source Files Referenced

- `app/Http/Controllers/ProjectDashboardController.php` (lines 48-224)
- `app/Http/Controllers/WorkOrderReportController.php` (lines 27-188)
- `app/Console/Commands/SyncToOpenSearch.php`
- `app/Services/OpenSearchService.php`
- `resources/views/project-dashboard/workorders.blade.php`
- `resources/views/reports/work-orders.blade.php`
- `routes/web.php`

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- ===== doc 01: work-orders KPI cube =====
        CREATE MATERIALIZED VIEW reports.mv_work_order_kpis AS
        SELECT
            d.iso_year_month                                   AS year_month,
            f.project_user_id,
            f.service_provider_id,
            f.asset_category_id,
            f.property_id,
            f.priority_id,
            f.work_order_type,
            f.service_type,
            f.workorder_journey,
            f.status_code,
            COUNT(*)                                           AS wo_count,
            COUNT(DISTINCT f.maintenance_request_id) FILTER (WHERE f.maintenance_request_id IS NOT NULL) AS maintenance_requests,
            COUNT(DISTINCT f.service_provider_id)    FILTER (WHERE f.service_provider_id > 0)            AS service_providers,
            COALESCE(SUM(f.cost), 0)                           AS total_cost
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

        -- ===== doc 02: properties =====
        CREATE MATERIALIZED VIEW reports.mv_property_kpis AS
        SELECT
            p.owner_user_id,
            p.region_id,
            p.city_id,
            p.property_type,
            p.location_type,
            to_char(p.created_at, 'YYYY-MM') AS year_month,
            COUNT(*)                              AS property_count,
            COALESCE(SUM(p.buildings_count),0)    AS buildings_count,
            COUNT(*) FILTER (WHERE p.is_active)   AS active_count,
            COUNT(*) FILTER (WHERE p.property_type='building') AS building_count,
            COUNT(*) FILTER (WHERE p.property_type='complex')  AS complex_count
        FROM marts.dim_property p
        WHERE NOT p.is_deleted
        GROUP BY CUBE(p.owner_user_id, p.region_id, p.city_id, p.property_type, p.location_type, to_char(p.created_at, 'YYYY-MM'));
        CREATE UNIQUE INDEX ix_mv_property_kpis ON reports.mv_property_kpis
            (owner_user_id, region_id, city_id, property_type, location_type, year_month);

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

        -- ===== doc 03: assets =====
        CREATE MATERIALIZED VIEW reports.mv_asset_kpis AS
        SELECT
            a.owner_user_id,
            a.asset_category_id,
            a.building_id,
            a.asset_status_id,
            a.asset_name_id,
            to_char(a.created_at, 'YYYY-MM') AS year_month,
            COUNT(*)                                       AS asset_count,
            COUNT(*) FILTER (WHERE a.has_status)           AS with_status,
            COUNT(*) FILTER (WHERE NOT a.has_status)       AS without_status,
            COUNT(*) FILTER (WHERE a.warranty_end_date IS NOT NULL AND a.warranty_end_date >= CURRENT_DATE) AS under_warranty,
            COUNT(DISTINCT a.asset_category_id)            AS distinct_categories,
            COUNT(DISTINCT a.building_id)                  AS distinct_buildings
        FROM marts.fact_asset a
        GROUP BY CUBE(
            a.owner_user_id, a.asset_category_id, a.building_id,
            a.asset_status_id, a.asset_name_id,
            to_char(a.created_at, 'YYYY-MM')
        );
        CREATE UNIQUE INDEX ix_mv_asset_kpis ON reports.mv_asset_kpis
            (owner_user_id, asset_category_id, building_id, asset_status_id, asset_name_id, year_month);

        CREATE MATERIALIZED VIEW reports.mv_asset_wo_cost AS
        SELECT
            a.asset_id,
            a.asset_tag,
            a.owner_user_id,
            a.asset_category_id,
            a.building_id,
            COUNT(wo.wo_id)                                       AS wo_count,
            COUNT(wo.wo_id) FILTER (WHERE wo.status_code = 4)     AS closed_wos,
            COALESCE(SUM(wo.cost), 0)                             AS lifetime_cost
        FROM marts.fact_asset a
        LEFT JOIN marts.fact_work_order wo
               ON wo.asset_name_id     = a.asset_name_id
              AND wo.asset_category_id = a.asset_category_id
              AND wo.property_id       = a.building_id
        GROUP BY a.asset_id, a.asset_tag, a.owner_user_id, a.asset_category_id, a.building_id;
        CREATE UNIQUE INDEX ix_mv_asset_wo_cost ON reports.mv_asset_wo_cost(asset_id);

        -- ===== doc 04: users =====
        CREATE MATERIALIZED VIEW reports.mv_user_kpis AS
        SELECT
            up.project_id,
            u.user_type,
            u.user_type_label,
            to_char(u.created_at, 'YYYY-MM') AS year_month,
            COUNT(*)                                AS user_count,
            COUNT(*) FILTER (WHERE u.is_active)     AS active_count,
            COUNT(*) FILTER (WHERE NOT u.is_active) AS inactive_count,
            COUNT(*) FILTER (WHERE u.is_deleted)    AS deleted_count
        FROM marts.dim_user u
        JOIN marts.bridge_user_project up ON up.user_id = u.user_id
        GROUP BY CUBE(up.project_id, u.user_type, u.user_type_label, to_char(u.created_at, 'YYYY-MM'));
        CREATE UNIQUE INDEX ix_mv_user_kpis ON reports.mv_user_kpis(project_id, user_type, user_type_label, year_month);

        -- ===== doc 05: billing =====
        CREATE MATERIALIZED VIEW reports.mv_billing_contract_totals AS
        SELECT
            c.project_id,
            c.contract_type,
            COUNT(*)                                            AS total_contracts,
            COUNT(*) FILTER (WHERE c.contract_type = 'rent')    AS rent_contracts,
            COUNT(*) FILTER (WHERE c.contract_type = 'lease')   AS lease_contracts,
            COUNT(*) FILTER (WHERE c.auto_renewal)              AS auto_renewal,
            COALESCE(SUM(c.amount), 0)                          AS total_value,
            COALESCE(SUM(c.security_deposit_amount), 0)         AS total_security,
            COALESCE(SUM(c.late_fees_charge), 0)                AS total_late_fees,
            COALESCE(SUM(c.brokerage_fee), 0)                   AS total_brokerage,
            COALESCE(SUM(c.retainer_fee), 0)                    AS total_retainer,
            COALESCE(SUM(c.payment_due), 0)                     AS total_payment_due,
            COALESCE(SUM(c.payment_overdue), 0)                 AS total_payment_overdue
        FROM marts.fact_commercial_contract c
        WHERE NOT c.is_deleted
        GROUP BY CUBE(c.project_id, c.contract_type);
        CREATE UNIQUE INDEX ix_mv_bct ON reports.mv_billing_contract_totals(project_id, contract_type);

        CREATE MATERIALIZED VIEW reports.mv_billing_installments AS
        WITH scoped AS (
            SELECT i.*, c.project_id
            FROM marts.fact_installment i
            JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
            WHERE NOT c.is_deleted
        )
        SELECT
            project_id,
            to_char(payment_due_date, 'YYYY-MM')                                              AS due_month,
            COUNT(*)                                                                          AS total_installments,
            COUNT(*) FILTER (WHERE is_paid)                                                   AS paid_count,
            COUNT(*) FILTER (WHERE NOT is_paid)                                               AS unpaid_count,
            COUNT(*) FILTER (WHERE NOT is_paid AND payment_due_date < CURRENT_DATE)           AS overdue_count,
            COUNT(*) FILTER (WHERE is_prepayment)                                             AS prepayments,
            COALESCE(SUM(amount) FILTER (WHERE is_paid), 0)                                   AS collected,
            COALESCE(SUM(amount) FILTER (WHERE NOT is_paid), 0)                               AS outstanding,
            COALESCE(SUM(amount) FILTER (WHERE NOT is_paid AND payment_due_date < CURRENT_DATE), 0) AS overdue_amount
        FROM scoped
        GROUP BY CUBE(project_id, to_char(payment_due_date, 'YYYY-MM'));
        CREATE UNIQUE INDEX ix_mv_bi ON reports.mv_billing_installments(project_id, due_month);

        CREATE MATERIALIZED VIEW reports.mv_billing_aging AS
        WITH scoped AS (
            SELECT i.amount, i.payment_due_date, i.is_paid, c.project_id
            FROM marts.fact_installment i
            JOIN marts.fact_commercial_contract c ON c.commercial_contract_id = i.commercial_contract_id
            WHERE NOT c.is_deleted AND NOT i.is_paid
        )
        SELECT
            project_id,
            SUM(amount) FILTER (WHERE payment_due_date >= CURRENT_DATE)                    AS future,
            SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 1 AND 30)  AS d30,
            SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 31 AND 60) AS d60,
            SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) BETWEEN 61 AND 90) AS d90,
            SUM(amount) FILTER (WHERE (CURRENT_DATE - payment_due_date) > 90)              AS d90_plus
        FROM scoped
        GROUP BY CUBE(project_id);
        CREATE UNIQUE INDEX ix_mv_aging ON reports.mv_billing_aging(project_id);

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

        -- ===== doc 06: contracts =====
        CREATE MATERIALIZED VIEW reports.mv_contract_totals AS
        SELECT
            dc.owner_user_id,
            dc.service_provider_id,
            dc.contract_type_id,
            COUNT(*)                                                  AS total_contracts,
            SUM(dc.contract_value)                                    AS total_value,
            AVG(dc.contract_value)                                    AS avg_value,
            COUNT(*) FILTER (WHERE dc.is_active)                      AS active_count,
            COUNT(*) FILTER (WHERE dc.parent_contract_id IS NOT NULL) AS subcontract_count,
            COUNT(*) FILTER (WHERE dc.end_date < CURRENT_DATE)        AS expired_count
        FROM marts.dim_contract dc
        WHERE dc.is_current AND NOT dc.is_deleted
        GROUP BY CUBE(dc.owner_user_id, dc.service_provider_id, dc.contract_type_id);
        CREATE UNIQUE INDEX ix_mv_ct ON reports.mv_contract_totals(owner_user_id, service_provider_id, contract_type_id);

        CREATE MATERIALIZED VIEW reports.mv_contract_payment_schedule AS
        SELECT
            dc.owner_user_id,
            fcm.contract_id,
            to_char(fcm.month, 'YYYY-MM')                                                                  AS year_month,
            COALESCE(SUM(fcm.amount), 0)                                                                   AS scheduled,
            COALESCE(SUM(fcm.amount) FILTER (WHERE fcm.is_paid), 0)                                        AS paid,
            COALESCE(SUM(fcm.amount) FILTER (WHERE NOT fcm.is_paid), 0)                                    AS pending,
            COALESCE(SUM(fcm.amount) FILTER (WHERE NOT fcm.is_paid AND fcm.month < CURRENT_DATE), 0)       AS overdue_amount,
            COUNT(*) FILTER (WHERE NOT fcm.is_paid AND fcm.month < CURRENT_DATE)                           AS overdue_count
        FROM marts.fact_contract_month fcm
        JOIN marts.dim_contract dc ON dc.contract_id = fcm.contract_id AND dc.is_current
        WHERE NOT dc.is_deleted
        GROUP BY CUBE(dc.owner_user_id, fcm.contract_id, to_char(fcm.month, 'YYYY-MM'));
        CREATE UNIQUE INDEX ix_mv_cps ON reports.mv_contract_payment_schedule(owner_user_id, contract_id, year_month);

        CREATE MATERIALIZED VIEW reports.mv_contract_wo_extras AS
        SELECT
            wo.contract_id,
            COUNT(*) FILTER (WHERE wo.status_code = 4)                  AS closed_wos,
            COALESCE(SUM(wo.cost) FILTER (WHERE wo.status_code = 4), 0) AS extras_total,
            COALESCE(SUM(wo.cost), 0)                                   AS total_cost,
            to_char(date_trunc('month', wo.created_at), 'YYYY-MM')      AS year_month
        FROM marts.fact_work_order wo
        WHERE wo.contract_id IS NOT NULL
        GROUP BY CUBE(wo.contract_id, to_char(date_trunc('month', wo.created_at), 'YYYY-MM'));
        CREATE UNIQUE INDEX ix_mv_cwx ON reports.mv_contract_wo_extras(contract_id, year_month);

        CREATE MATERIALIZED VIEW reports.mv_contract_top_overdue AS
        SELECT
            fcm.contract_id,
            dc.contract_number,
            dc.service_provider_id,
            COUNT(*)                AS overdue_months,
            SUM(fcm.amount)         AS overdue_amount
        FROM marts.fact_contract_month fcm
        JOIN marts.dim_contract dc ON dc.contract_id = fcm.contract_id AND dc.is_current
        WHERE NOT fcm.is_paid
          AND fcm.month < CURRENT_DATE
          AND NOT dc.is_deleted
        GROUP BY fcm.contract_id, dc.contract_number, dc.service_provider_id;
        CREATE INDEX ix_mv_cto ON reports.mv_contract_top_overdue(overdue_amount DESC);

        CREATE MATERIALIZED VIEW reports.mv_contract_payroll_status AS
        SELECT
            p.contract_id,
            p.file_status,
            COUNT(*) AS cnt
        FROM marts.fact_contract_payroll p
        GROUP BY p.contract_id, p.file_status;

        -- ===== doc 07: overview =====
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
            u.full_name                          AS owner_name,
            COALESCE(pc.property_count, 0)       AS property_count,
            COALESCE(sc.sp_count, 0)             AS sp_count,
            COALESCE(cv.total_contract_value, 0) AS contract_value,
            COALESCE(lm.payment_due, 0)          AS payment_due,
            COALESCE(lm.payment_overdue, 0)      AS payment_overdue,
            COALESCE(lm.lease_value, 0)          AS lease_value
        FROM marts.dim_project p
        LEFT JOIN marts.dim_user u    ON u.user_id    = p.owner_user_id
        LEFT JOIN prop_counts    pc   ON pc.project_id = p.project_id
        LEFT JOIN sp_counts      sc   ON sc.project_id = p.project_id
        LEFT JOIN contract_value cv   ON cv.project_id = p.project_id
        LEFT JOIN lease_money    lm   ON lm.project_id = p.project_id;
        CREATE UNIQUE INDEX ix_mv_proj_rollup      ON reports.mv_project_rollup(project_id);
        CREATE INDEX        ix_mv_proj_rollup_name ON reports.mv_project_rollup(project_name);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_project_rollup           CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_overview_totals          CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_contract_payroll_status  CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_contract_top_overdue     CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_contract_wo_extras       CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_contract_payment_schedule CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_contract_totals          CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_billing_top_tenants      CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_billing_aging            CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_billing_installments     CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_billing_contract_totals  CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_user_kpis                CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_asset_wo_cost            CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_asset_kpis               CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_property_contract_rollup CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_property_kpis            CASCADE;
        DROP MATERIALIZED VIEW IF EXISTS reports.mv_work_order_kpis          CASCADE;
        SQL);
    }
};

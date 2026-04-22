<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TYPE marts.wo_type_enum       AS ENUM ('reactive','preventive');
        CREATE TYPE marts.wo_service_enum    AS ENUM ('soft','hard');
        CREATE TYPE marts.wo_contract_enum   AS ENUM ('regular','warranty');
        CREATE TYPE marts.wo_journey_enum    AS ENUM ('submitted','job_execution','job_evaluation','job_approval','finished');
        CREATE TYPE marts.wo_pass_fail_enum  AS ENUM ('pass','fail','pending');
        CREATE TYPE marts.wo_time_unit_enum  AS ENUM ('days','hours','minutes');

        CREATE TABLE marts.fact_work_order (
            wo_id                  BIGINT NOT NULL,
            wo_number              VARCHAR(100) NOT NULL,
            project_user_id        BIGINT REFERENCES marts.dim_user(user_id) DEFERRABLE INITIALLY DEFERRED,
            service_provider_id    BIGINT REFERENCES marts.dim_service_provider(sp_id) DEFERRABLE INITIALLY DEFERRED,
            property_id            BIGINT REFERENCES marts.dim_property_building(building_id) DEFERRABLE INITIALLY DEFERRED,
            unit_id                INT,
            asset_category_id      BIGINT REFERENCES marts.dim_asset_category(asset_category_id) DEFERRABLE INITIALLY DEFERRED,
            asset_name_id          BIGINT REFERENCES marts.dim_asset_name(asset_name_id) DEFERRABLE INITIALLY DEFERRED,
            priority_id            BIGINT REFERENCES marts.dim_priority(priority_id) DEFERRABLE INITIALLY DEFERRED,
            contract_id            BIGINT,
            contract_type          marts.wo_contract_enum,
            maintenance_request_id INT,
            work_order_type        marts.wo_type_enum,
            service_type           marts.wo_service_enum,
            workorder_journey      marts.wo_journey_enum,
            status_code            SMALLINT,
            status_label           TEXT,
            cost                   NUMERIC(18,2) NOT NULL DEFAULT 0,
            score                  DOUBLE PRECISION NOT NULL DEFAULT 0,
            pass_fail              marts.wo_pass_fail_enum,
            sla_response_time      NUMERIC,
            response_time_type     marts.wo_time_unit_enum,
            sla_service_window     INT,
            service_window_type    marts.wo_time_unit_enum,
            created_date_key       DATE GENERATED ALWAYS AS ((created_at AT TIME ZONE 'UTC')::date) STORED,
            start_date             DATE,
            end_date               DATE,
            target_at              TIMESTAMPTZ,
            job_started_at         TIMESTAMPTZ,
            job_submitted_at       TIMESTAMPTZ,
            job_completion_at      TIMESTAMPTZ,
            created_at             TIMESTAMPTZ NOT NULL,
            source_updated_at      TIMESTAMPTZ NOT NULL,
            loaded_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (wo_id, created_at),
            UNIQUE (wo_number, created_at),
            FOREIGN KEY (created_date_key) REFERENCES marts.dim_date(date_key) DEFERRABLE INITIALLY DEFERRED
        ) PARTITION BY RANGE (created_at);

        CREATE TABLE marts.fact_work_order_y2024 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
        CREATE TABLE marts.fact_work_order_y2025 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
        CREATE TABLE marts.fact_work_order_y2026 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
        CREATE TABLE marts.fact_work_order_y2027 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');
        CREATE TABLE marts.fact_work_order_y2028 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2028-01-01') TO ('2029-01-01');
        CREATE TABLE marts.fact_work_order_y2029 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2029-01-01') TO ('2030-01-01');
        CREATE TABLE marts.fact_work_order_y2030 PARTITION OF marts.fact_work_order
            FOR VALUES FROM ('2030-01-01') TO ('2031-01-01');

        CREATE INDEX ix_fwo_created_date ON marts.fact_work_order(created_date_key);
        CREATE INDEX ix_fwo_sp           ON marts.fact_work_order(service_provider_id);
        CREATE INDEX ix_fwo_category     ON marts.fact_work_order(asset_category_id);
        CREATE INDEX ix_fwo_property     ON marts.fact_work_order(property_id);
        CREATE INDEX ix_fwo_priority     ON marts.fact_work_order(priority_id);
        CREATE INDEX ix_fwo_project_user ON marts.fact_work_order(project_user_id);
        CREATE INDEX ix_fwo_status       ON marts.fact_work_order(status_code);
        CREATE INDEX ix_fwo_journey      ON marts.fact_work_order(workorder_journey);
        CREATE INDEX ix_fwo_mr           ON marts.fact_work_order(maintenance_request_id);
        CREATE INDEX ix_fwo_contract     ON marts.fact_work_order(contract_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.fact_work_order CASCADE;
        DROP TYPE IF EXISTS marts.wo_time_unit_enum CASCADE;
        DROP TYPE IF EXISTS marts.wo_pass_fail_enum CASCADE;
        DROP TYPE IF EXISTS marts.wo_journey_enum   CASCADE;
        DROP TYPE IF EXISTS marts.wo_contract_enum  CASCADE;
        DROP TYPE IF EXISTS marts.wo_service_enum   CASCADE;
        DROP TYPE IF EXISTS marts.wo_type_enum      CASCADE;
        SQL);
    }
};

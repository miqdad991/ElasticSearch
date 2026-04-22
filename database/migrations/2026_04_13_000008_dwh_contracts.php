<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE marts.dim_contract_type (
            contract_type_id BIGINT PRIMARY KEY,
            name             TEXT NOT NULL,
            slug             TEXT,
            is_advance       BOOLEAN GENERATED ALWAYS AS (contract_type_id IN (6,7)) STORED,
            loaded_at        TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_payroll_type (
            payroll_type_id INT PRIMARY KEY,
            name            TEXT NOT NULL,
            loaded_at       TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_akaunting_map (
            osool_document_id     BIGINT NOT NULL,
            document_type         TEXT   NOT NULL,
            akaunting_document_id BIGINT NOT NULL,
            loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (osool_document_id, document_type)
        );

        CREATE TYPE marts.file_status_enum AS ENUM ('Pending','Review','Approved','Rejected');

        CREATE TABLE marts.dim_contract (
            contract_sk          BIGSERIAL PRIMARY KEY,
            contract_id          BIGINT NOT NULL,
            valid_from           TIMESTAMPTZ NOT NULL,
            valid_to             TIMESTAMPTZ NOT NULL DEFAULT 'infinity',
            is_current           BOOLEAN NOT NULL DEFAULT TRUE,
            contract_number      TEXT,
            parent_contract_id   BIGINT,
            owner_user_id        BIGINT REFERENCES marts.dim_user(user_id),
            service_provider_id  BIGINT REFERENCES marts.dim_service_provider(sp_id),
            contract_type_id     BIGINT REFERENCES marts.dim_contract_type(contract_type_id),
            start_date           DATE,
            end_date             DATE,
            contract_value       NUMERIC(18,2) NOT NULL DEFAULT 0,
            retention_percent    NUMERIC(5,2)  NOT NULL DEFAULT 0,
            discount_percent     NUMERIC(5,2)  NOT NULL DEFAULT 0,
            spare_parts_included BOOLEAN NOT NULL DEFAULT FALSE,
            allow_subcontract    BOOLEAN NOT NULL DEFAULT FALSE,
            workers_count        INT NOT NULL DEFAULT 0,
            supervisor_count     INT NOT NULL DEFAULT 0,
            administrator_count  INT NOT NULL DEFAULT 0,
            engineer_count       INT NOT NULL DEFAULT 0,
            comment              TEXT,
            file_path            TEXT,
            status               SMALLINT NOT NULL DEFAULT 0,
            is_active            BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
            is_deleted           BOOLEAN NOT NULL DEFAULT FALSE,
            source_updated_at    TIMESTAMPTZ NOT NULL,
            loaded_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
            UNIQUE (contract_id, valid_from)
        );
        CREATE UNIQUE INDEX ux_dim_contract_current ON marts.dim_contract(contract_id) WHERE is_current;
        CREATE INDEX ix_dim_contract_sp      ON marts.dim_contract(service_provider_id);
        CREATE INDEX ix_dim_contract_type    ON marts.dim_contract(contract_type_id);
        CREATE INDEX ix_dim_contract_owner   ON marts.dim_contract(owner_user_id);
        CREATE INDEX ix_dim_contract_parent  ON marts.dim_contract(parent_contract_id);
        CREATE INDEX ix_dim_contract_natural ON marts.dim_contract(contract_id, valid_from DESC);

        -- Bridges (target the natural contract_id)
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
            contract_id  BIGINT NOT NULL,
            item_id      BIGINT NOT NULL,
            company_id   BIGINT,
            warehouse_id BIGINT,
            PRIMARY KEY (contract_id, item_id)
        );

        -- Facts
        CREATE TABLE marts.fact_contract_priority (
            id                  BIGINT PRIMARY KEY,
            contract_id         BIGINT NOT NULL,
            priority_id         BIGINT REFERENCES marts.dim_priority(priority_id),
            service_window      INT,
            service_window_type marts.wo_time_unit_enum,
            response_time       NUMERIC,
            response_time_type  marts.wo_time_unit_enum,
            created_at          TIMESTAMPTZ NOT NULL,
            loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcp_contract ON marts.fact_contract_priority(contract_id);

        CREATE TABLE marts.fact_contract_month (
            contract_month_id    BIGINT NOT NULL,
            contract_id          BIGINT NOT NULL,
            user_id              BIGINT,
            month                DATE NOT NULL,
            amount               NUMERIC(18,2) NOT NULL DEFAULT 0,
            is_paid              BOOLEAN NOT NULL DEFAULT FALSE,
            is_extended_contract BOOLEAN NOT NULL DEFAULT FALSE,
            bill_id              BIGINT,
            created_at           TIMESTAMPTZ NOT NULL,
            source_updated_at    TIMESTAMPTZ,
            loaded_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (contract_month_id, month)
        ) PARTITION BY RANGE (month);

        CREATE TABLE marts.fact_contract_month_y2024 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
        CREATE TABLE marts.fact_contract_month_y2025 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
        CREATE TABLE marts.fact_contract_month_y2026 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
        CREATE TABLE marts.fact_contract_month_y2027 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');
        CREATE TABLE marts.fact_contract_month_y2028 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2028-01-01') TO ('2029-01-01');
        CREATE TABLE marts.fact_contract_month_y2029 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2029-01-01') TO ('2030-01-01');
        CREATE TABLE marts.fact_contract_month_y2030 PARTITION OF marts.fact_contract_month FOR VALUES FROM ('2030-01-01') TO ('2031-01-01');

        CREATE INDEX ix_fcm_contract ON marts.fact_contract_month(contract_id);
        CREATE INDEX ix_fcm_unpaid   ON marts.fact_contract_month(month) WHERE NOT is_paid;

        CREATE TABLE marts.fact_contract_payroll (
            payroll_id           BIGINT PRIMARY KEY,
            contract_id          BIGINT NOT NULL,
            payroll_type_id      INT REFERENCES marts.dim_payroll_type(payroll_type_id),
            payroll_type_label   TEXT,
            project_user_id      BIGINT REFERENCES marts.dim_user(user_id),
            service_provider_id  BIGINT REFERENCES marts.dim_service_provider(sp_id),
            payroll_group_id     TEXT,
            file_path            TEXT,
            file_status          marts.file_status_enum NOT NULL,
            rejection_reason     TEXT,
            scheduled            TEXT,
            archived             BOOLEAN NOT NULL DEFAULT FALSE,
            created_at           TIMESTAMPTZ NOT NULL,
            source_updated_at    TIMESTAMPTZ,
            loaded_at            TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcpr_contract ON marts.fact_contract_payroll(contract_id);
        CREATE INDEX ix_fcpr_status   ON marts.fact_contract_payroll(file_status);

        CREATE TABLE marts.fact_contract_payroll_rejection (
            rejection_id     BIGINT PRIMARY KEY,
            payroll_id       BIGINT REFERENCES marts.fact_contract_payroll(payroll_id) ON DELETE CASCADE,
            file_status      marts.file_status_enum,
            rejection_reason TEXT,
            created_at       TIMESTAMPTZ NOT NULL,
            loaded_at        TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.fact_contract_document (
            document_id      BIGINT PRIMARY KEY,
            contract_id      BIGINT NOT NULL,
            document_type_id INT,
            file_path        TEXT,
            file_status      marts.file_status_enum,
            archived         BOOLEAN NOT NULL DEFAULT FALSE,
            created_at       TIMESTAMPTZ NOT NULL,
            loaded_at        TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcd_contract ON marts.fact_contract_document(contract_id);

        CREATE TABLE marts.fact_contract_inspection_report (
            report_id        BIGINT PRIMARY KEY,
            contract_id      BIGINT NOT NULL,
            report_type_id   INT,
            schedule_type_id INT,
            file_status      marts.file_status_enum,
            file_paths       JSONB NOT NULL DEFAULT '[]'::jsonb,
            archived         BOOLEAN NOT NULL DEFAULT FALSE,
            created_at       TIMESTAMPTZ NOT NULL,
            loaded_at        TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcir_contract ON marts.fact_contract_inspection_report(contract_id);

        CREATE TABLE marts.fact_contract_kpi (
            kpi_id                BIGINT PRIMARY KEY,
            contract_id           BIGINT NOT NULL,
            performance_indicator JSONB,
            range_id              INT,
            created_at            TIMESTAMPTZ NOT NULL,
            loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fck_contract ON marts.fact_contract_kpi(contract_id);
        CREATE INDEX ix_fck_pi_gin   ON marts.fact_contract_kpi USING GIN (performance_indicator);

        CREATE TABLE marts.fact_contract_service_kpi (
            id                    BIGINT PRIMARY KEY,
            contract_id           BIGINT NOT NULL,
            service_id            BIGINT NOT NULL,
            performance_indicator JSONB,
            price                 NUMERIC(18,2),
            description           TEXT,
            created_at            TIMESTAMPTZ NOT NULL,
            source_updated_at     TIMESTAMPTZ,
            loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcsk_contract ON marts.fact_contract_service_kpi(contract_id);
        CREATE INDEX ix_fcsk_pi_gin   ON marts.fact_contract_service_kpi USING GIN (performance_indicator);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.fact_contract_service_kpi        CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_kpi                CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_inspection_report  CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_document           CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_payroll_rejection  CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_payroll            CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_month              CASCADE;
        DROP TABLE IF EXISTS marts.fact_contract_priority           CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_item             CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_property_building CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_asset_name       CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_asset_category   CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_city             CASCADE;
        DROP TABLE IF EXISTS marts.bridge_contract_region           CASCADE;
        DROP TABLE IF EXISTS marts.dim_contract                     CASCADE;
        DROP TYPE  IF EXISTS marts.file_status_enum                 CASCADE;
        DROP TABLE IF EXISTS marts.dim_akaunting_map                CASCADE;
        DROP TABLE IF EXISTS marts.dim_payroll_type                 CASCADE;
        DROP TABLE IF EXISTS marts.dim_contract_type                CASCADE;
        SQL);
    }
};

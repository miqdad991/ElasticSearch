<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Calendar dim
        CREATE TABLE marts.dim_date (
            date_key         DATE PRIMARY KEY,
            year             SMALLINT NOT NULL,
            quarter          SMALLINT NOT NULL,
            month            SMALLINT NOT NULL,
            month_name       TEXT     NOT NULL,
            week             SMALLINT NOT NULL,
            day_of_month     SMALLINT NOT NULL,
            day_of_week      SMALLINT NOT NULL,
            is_weekend       BOOLEAN  NOT NULL,
            iso_year_month   CHAR(7) NOT NULL
        );
        CREATE INDEX ix_dim_date_ym ON marts.dim_date(iso_year_month);

        -- Geography
        CREATE TABLE marts.dim_region (
            region_id   INT PRIMARY KEY,
            name        TEXT NOT NULL,
            name_ar     TEXT,
            code        TEXT,
            country_id  INT,
            latitude    NUMERIC(10,6),
            longitude   NUMERIC(10,6),
            status      SMALLINT,
            is_deleted  BOOLEAN NOT NULL DEFAULT FALSE,
            loaded_at   TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_city (
            city_id     BIGINT PRIMARY KEY,
            name_en     TEXT NOT NULL,
            name_ar     TEXT,
            code        TEXT,
            postal_code TEXT,
            region_id   INT REFERENCES marts.dim_region(region_id),
            country_id  BIGINT,
            status      SMALLINT,
            is_deleted  BOOLEAN NOT NULL DEFAULT FALSE,
            loaded_at   TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_dim_city_region ON marts.dim_city(region_id);

        -- Users
        CREATE TYPE marts.user_type_enum AS ENUM (
            'super_admin','osool_admin','admin','admin_employee',
            'building_manager','building_manager_employee',
            'sp_admin','supervisor','sp_worker','tenant',
            'procurement_admin','manual_custodian','team_leader'
        );
        CREATE TYPE marts.device_type_enum AS ENUM ('android','ios');
        CREATE TYPE marts.language_enum    AS ENUM ('Arabic','English');

        CREATE TABLE marts.dim_user (
            user_id               BIGINT PRIMARY KEY,
            email                 CITEXT NOT NULL UNIQUE,
            full_name             TEXT,
            first_name            TEXT,
            last_name             TEXT,
            phone                 TEXT,
            profile_image_url     TEXT,
            emp_id                TEXT,
            user_type             marts.user_type_enum,
            user_type_label       TEXT,
            project_user_id       BIGINT,
            sp_admin_id           INT,
            service_provider      TEXT,
            country_id            BIGINT,
            city_id               BIGINT REFERENCES marts.dim_city(city_id),
            status                SMALLINT NOT NULL DEFAULT 1,
            is_active             BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
            is_deleted            BOOLEAN NOT NULL DEFAULT FALSE,
            deleted_at            TIMESTAMPTZ,
            created_at            TIMESTAMPTZ NOT NULL,
            source_updated_at     TIMESTAMPTZ,
            last_login_at         TIMESTAMPTZ,
            device_type           marts.device_type_enum,
            preferred_language    TEXT,
            sms_language          marts.language_enum,
            approved_max_amount   NUMERIC(15,2),
            salary                NUMERIC(10,2),
            allow_akaunting       BOOLEAN NOT NULL DEFAULT FALSE,
            akaunting_vendor_id   BIGINT,
            akaunting_customer_id BIGINT,
            created_by            BIGINT,
            loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        ALTER TABLE marts.dim_user
            ADD CONSTRAINT fk_dim_user_project_user
            FOREIGN KEY (project_user_id) REFERENCES marts.dim_user(user_id)
            DEFERRABLE INITIALLY DEFERRED;
        ALTER TABLE marts.dim_user
            ADD CONSTRAINT fk_dim_user_created_by
            FOREIGN KEY (created_by) REFERENCES marts.dim_user(user_id)
            DEFERRABLE INITIALLY DEFERRED;
        CREATE INDEX ix_dim_user_type       ON marts.dim_user(user_type);
        CREATE INDEX ix_dim_user_project    ON marts.dim_user(project_user_id);
        CREATE INDEX ix_dim_user_sp_admin   ON marts.dim_user(sp_admin_id);
        CREATE INDEX ix_dim_user_created_at ON marts.dim_user(created_at);

        -- Project (full version from doc #7)
        CREATE TABLE marts.dim_project (
            project_id              INT PRIMARY KEY,
            owner_user_id           BIGINT REFERENCES marts.dim_user(user_id),
            project_name            TEXT NOT NULL,
            industry_type           TEXT,
            contract_status         TEXT,
            contract_start_date     DATE,
            contract_end_date       DATE,
            use_erp_module          BOOLEAN NOT NULL DEFAULT FALSE,
            use_crm_module          BOOLEAN NOT NULL DEFAULT FALSE,
            use_tenant_module       BOOLEAN NOT NULL DEFAULT FALSE,
            use_beneficiary_module  BOOLEAN NOT NULL DEFAULT FALSE,
            enable_crm_projects     BOOLEAN NOT NULL DEFAULT FALSE,
            enable_crm_sales        BOOLEAN NOT NULL DEFAULT FALSE,
            enable_crm_finance      BOOLEAN NOT NULL DEFAULT FALSE,
            enable_crm_rfx          BOOLEAN NOT NULL DEFAULT FALSE,
            enable_crm_documents    BOOLEAN NOT NULL DEFAULT FALSE,
            is_active               BOOLEAN NOT NULL DEFAULT TRUE,
            is_deleted              BOOLEAN NOT NULL DEFAULT FALSE,
            created_at              TIMESTAMPTZ NOT NULL,
            source_updated_at       TIMESTAMPTZ,
            loaded_at               TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_dim_project_owner  ON marts.dim_project(owner_user_id);
        CREATE INDEX ix_dim_project_active ON marts.dim_project(is_active);

        CREATE TABLE marts.bridge_user_project (
            user_id    BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            project_id INT    NOT NULL REFERENCES marts.dim_project(project_id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, project_id)
        );
        CREATE INDEX ix_bup_project ON marts.bridge_user_project(project_id);

        -- Property
        CREATE TYPE marts.property_type_enum AS ENUM ('building','complex');
        CREATE TYPE marts.location_type_enum AS ENUM ('single_location','multiple_location');

        CREATE TABLE marts.dim_property (
            property_id            BIGINT PRIMARY KEY,
            owner_user_id          BIGINT REFERENCES marts.dim_user(user_id),
            property_name          TEXT NOT NULL,
            property_tag           TEXT,
            property_number        TEXT,
            compound_name          TEXT,
            property_type          marts.property_type_enum,
            location_type          marts.location_type_enum,
            property_usage         TEXT,
            region_id              INT REFERENCES marts.dim_region(region_id),
            city_id                BIGINT REFERENCES marts.dim_city(city_id),
            district_name          TEXT,
            street_name            TEXT,
            postal_code            TEXT,
            building_number        TEXT,
            latitude               NUMERIC(10,6),
            longitude              NUMERIC(10,6),
            location_label         TEXT,
            buildings_count        INT NOT NULL DEFAULT 0,
            actual_buildings_added INT,
            total_floors           INT,
            units_per_floor        INT,
            total_units            INT,
            established_date       DATE,
            awqaf_contains         BOOLEAN NOT NULL DEFAULT FALSE,
            worker_housing         BOOLEAN NOT NULL DEFAULT FALSE,
            agreement_status       TEXT,
            contract_type          TEXT,
            status                 SMALLINT,
            is_active              BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
            is_deleted             BOOLEAN NOT NULL DEFAULT FALSE,
            created_at             TIMESTAMPTZ NOT NULL,
            source_updated_at      TIMESTAMPTZ,
            loaded_at              TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_dim_property_owner   ON marts.dim_property(owner_user_id);
        CREATE INDEX ix_dim_property_region  ON marts.dim_property(region_id);
        CREATE INDEX ix_dim_property_city    ON marts.dim_property(city_id);
        CREATE INDEX ix_dim_property_type    ON marts.dim_property(property_type);
        CREATE INDEX ix_dim_property_created ON marts.dim_property(created_at);

        CREATE TABLE marts.dim_property_building (
            building_id               BIGINT PRIMARY KEY,
            property_id               BIGINT REFERENCES marts.dim_property(property_id) ON DELETE SET NULL,
            building_name             TEXT NOT NULL,
            building_tag              TEXT,
            rooms_count               SMALLINT NOT NULL DEFAULT 0,
            use_building              SMALLINT,
            district_name             TEXT,
            street_name               TEXT,
            latitude                  NUMERIC(10,6),
            longitude                 NUMERIC(10,6),
            location_label            TEXT,
            barcode_value             TEXT,
            ownership_document_type   TEXT,
            ownership_document_number TEXT,
            ownership_issue_date      DATE,
            is_deleted                BOOLEAN NOT NULL DEFAULT FALSE,
            created_at                TIMESTAMPTZ NOT NULL,
            source_updated_at         TIMESTAMPTZ,
            loaded_at                 TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_dim_building_property ON marts.dim_property_building(property_id);

        -- Service provider, asset & priority dims (from doc #1)
        CREATE TABLE marts.dim_service_provider (
            sp_id             BIGINT PRIMARY KEY,
            name              TEXT NOT NULL,
            status            SMALLINT,
            is_deleted        BOOLEAN NOT NULL DEFAULT FALSE,
            source_updated_at TIMESTAMPTZ,
            loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_asset_category (
            asset_category_id BIGINT PRIMARY KEY,
            asset_category    TEXT NOT NULL,
            service_type      TEXT,
            status            SMALLINT,
            source_updated_at TIMESTAMPTZ,
            loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_asset_name (
            asset_name_id BIGINT PRIMARY KEY,
            asset_name    TEXT NOT NULL,
            loaded_at     TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_priority (
            priority_id         BIGINT PRIMARY KEY,
            priority_level      TEXT NOT NULL,
            service_window      INT,
            service_window_type TEXT,
            response_time       NUMERIC,
            response_time_type  TEXT,
            loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.dim_priority           CASCADE;
        DROP TABLE IF EXISTS marts.dim_asset_name         CASCADE;
        DROP TABLE IF EXISTS marts.dim_asset_category     CASCADE;
        DROP TABLE IF EXISTS marts.dim_service_provider   CASCADE;
        DROP TABLE IF EXISTS marts.dim_property_building  CASCADE;
        DROP TABLE IF EXISTS marts.dim_property           CASCADE;
        DROP TYPE  IF EXISTS marts.location_type_enum     CASCADE;
        DROP TYPE  IF EXISTS marts.property_type_enum     CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_project    CASCADE;
        DROP TABLE IF EXISTS marts.dim_project            CASCADE;
        DROP TABLE IF EXISTS marts.dim_user               CASCADE;
        DROP TYPE  IF EXISTS marts.language_enum          CASCADE;
        DROP TYPE  IF EXISTS marts.device_type_enum       CASCADE;
        DROP TYPE  IF EXISTS marts.user_type_enum         CASCADE;
        DROP TABLE IF EXISTS marts.dim_city               CASCADE;
        DROP TABLE IF EXISTS marts.dim_region             CASCADE;
        DROP TABLE IF EXISTS marts.dim_date               CASCADE;
        SQL);
    }
};

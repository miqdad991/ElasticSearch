<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- doc 01
        CREATE TABLE raw.work_orders         (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.service_providers   (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.asset_categories    (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.asset_names         (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.priorities          (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.property_buildings  (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.users               (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.projects_details    (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.user_projects (
            user_id     BIGINT NOT NULL,
            project_id  BIGINT NOT NULL,
            ingested_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (user_id, project_id)
        );
        CREATE TABLE raw.work_orders_dlq (
            id          BIGSERIAL PRIMARY KEY,
            payload     JSONB NOT NULL,
            error       TEXT,
            ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        -- doc 02
        CREATE TABLE raw.properties (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.regions    (id INT    PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.cities     (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());

        -- doc 03
        CREATE TABLE raw.assets         (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.asset_statuses (id INT    PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());

        -- doc 04
        CREATE TABLE raw.user_type (slug TEXT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());

        -- doc 05
        CREATE TABLE raw.commercial_contracts    (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.payment_details         (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());
        CREATE TABLE raw.lease_contract_details  (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now());

        -- doc 06
        CREATE TABLE raw.contracts                       (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_types                  (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_months                 (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_months_dlq (
            id          BIGSERIAL PRIMARY KEY,
            payload     JSONB NOT NULL,
            error       TEXT,
            ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE TABLE raw.contract_priorities             (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_asset_categories       (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_property_buildings     (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_usable_items           (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_payrolls               (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_payroll_rejections     (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_payroll_types          (id INT    PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_documents              (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_inspection_reports     (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_performance_indicators (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.contract_service_kpi            (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.mapping_osool_akaunting (
            osool_document_id BIGINT NOT NULL,
            document_type     TEXT NOT NULL,
            payload           JSONB NOT NULL,
            ingested_at       TIMESTAMPTZ DEFAULT now(),
            PRIMARY KEY (osool_document_id, document_type)
        );

        -- doc 07
        CREATE TABLE raw.packages (id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ DEFAULT now());
        CREATE TABLE raw.service_providers_project_mapping (
            service_provider_id BIGINT NOT NULL,
            project_id          INT    NOT NULL,
            ingested_at         TIMESTAMPTZ DEFAULT now(),
            PRIMARY KEY (service_provider_id, project_id)
        );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP SCHEMA IF EXISTS raw CASCADE;
        CREATE SCHEMA raw;
        SQL);
    }
};

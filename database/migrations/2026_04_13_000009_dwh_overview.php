<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE marts.dim_subscription_package (
            package_id        BIGINT PRIMARY KEY,
            name              TEXT NOT NULL,
            pricing_model     TEXT,
            price             NUMERIC(18,2) NOT NULL DEFAULT 0,
            discount          NUMERIC(5,2)  NOT NULL DEFAULT 0,
            effective_price   NUMERIC(18,2) GENERATED ALWAYS AS
                                  (CASE WHEN discount > 0
                                        THEN price - (price * discount / 100)
                                        ELSE price END) STORED,
            status            TEXT,
            is_active         BOOLEAN GENERATED ALWAYS AS (status = 'active') STORED,
            most_popular      BOOLEAN NOT NULL DEFAULT FALSE,
            created_at        TIMESTAMPTZ NOT NULL,
            source_updated_at TIMESTAMPTZ,
            loaded_at         TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_dim_sub_active ON marts.dim_subscription_package(is_active);

        CREATE TABLE marts.bridge_sp_project (
            service_provider_id BIGINT NOT NULL REFERENCES marts.dim_service_provider(sp_id) ON DELETE CASCADE,
            project_id          INT    NOT NULL REFERENCES marts.dim_project(project_id)     ON DELETE CASCADE,
            PRIMARY KEY (service_provider_id, project_id)
        );
        CREATE INDEX ix_bsp_project ON marts.bridge_sp_project(project_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.bridge_sp_project          CASCADE;
        DROP TABLE IF EXISTS marts.dim_subscription_package   CASCADE;
        SQL);
    }
};

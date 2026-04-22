<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE marts.dim_asset_status (
            asset_status_id INT PRIMARY KEY,
            name            TEXT NOT NULL,
            color           TEXT,
            owner_user_id   BIGINT REFERENCES marts.dim_user(user_id),
            is_deleted      BOOLEAN NOT NULL DEFAULT FALSE,
            loaded_at       TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TYPE marts.asset_threshold_unit_enum AS ENUM ('days','hours');

        CREATE TABLE marts.fact_asset (
            asset_id                 BIGINT PRIMARY KEY,
            asset_tag                VARCHAR(150) NOT NULL,
            asset_symbol             TEXT,
            asset_number             TEXT,
            barcode_value            TEXT,
            owner_user_id            BIGINT REFERENCES marts.dim_user(user_id),
            property_id              BIGINT REFERENCES marts.dim_property(property_id) ON DELETE SET NULL,
            building_id              BIGINT REFERENCES marts.dim_property_building(building_id) ON DELETE SET NULL,
            unit_id                  INT,
            floor                    TEXT,
            room                     TEXT,
            asset_category_id        BIGINT REFERENCES marts.dim_asset_category(asset_category_id),
            asset_name_id            BIGINT REFERENCES marts.dim_asset_name(asset_name_id),
            asset_status_id          INT    REFERENCES marts.dim_asset_status(asset_status_id),
            asset_status_raw         TEXT,
            model_number             TEXT,
            manufacturer_name        TEXT,
            purchase_date            DATE,
            purchase_amount          NUMERIC(15,2),
            warranty_duration_months INT,
            warranty_end_date        DATE,
            asset_damage_date        DATE,
            usage_threshold          INT,
            threshold_unit_value     marts.asset_threshold_unit_enum,
            hours_per_day            INT,
            days_per_week            SMALLINT,
            usage_start_at           TIMESTAMPTZ,
            last_usage_reset_at      TIMESTAMPTZ,
            linked_wo                BOOLEAN NOT NULL DEFAULT FALSE,
            warehouse_id             BIGINT,
            inventory_id             INT,
            converted_assets         INT NOT NULL DEFAULT 0,
            related_to               SMALLINT,
            has_status               BOOLEAN GENERATED ALWAYS AS
                                        (asset_status_raw IS NOT NULL AND asset_status_raw <> '') STORED,
            created_date_key         DATE GENERATED ALWAYS AS ((created_at AT TIME ZONE 'UTC')::date) STORED,
            created_at               TIMESTAMPTZ NOT NULL,
            source_updated_at        TIMESTAMPTZ,
            loaded_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
            FOREIGN KEY (created_date_key) REFERENCES marts.dim_date(date_key) DEFERRABLE INITIALLY DEFERRED
        );

        CREATE INDEX ix_fa_owner    ON marts.fact_asset(owner_user_id);
        CREATE INDEX ix_fa_property ON marts.fact_asset(property_id);
        CREATE INDEX ix_fa_building ON marts.fact_asset(building_id);
        CREATE INDEX ix_fa_category ON marts.fact_asset(asset_category_id);
        CREATE INDEX ix_fa_name     ON marts.fact_asset(asset_name_id);
        CREATE INDEX ix_fa_status   ON marts.fact_asset(asset_status_id);
        CREATE INDEX ix_fa_created  ON marts.fact_asset(created_date_key);
        CREATE INDEX ix_fa_warranty ON marts.fact_asset(warranty_end_date) WHERE warranty_end_date IS NOT NULL;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.fact_asset CASCADE;
        DROP TYPE  IF EXISTS marts.asset_threshold_unit_enum CASCADE;
        DROP TABLE IF EXISTS marts.dim_asset_status CASCADE;
        SQL);
    }
};

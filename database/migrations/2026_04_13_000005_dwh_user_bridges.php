<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE marts.bridge_user_building (
            user_id     BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            building_id BIGINT NOT NULL REFERENCES marts.dim_property_building(building_id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, building_id)
        );
        CREATE INDEX ix_bub_building ON marts.bridge_user_building(building_id);

        CREATE TABLE marts.bridge_user_contract (
            user_id     BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            contract_id BIGINT NOT NULL,
            PRIMARY KEY (user_id, contract_id)
        );
        CREATE INDEX ix_buc_contract ON marts.bridge_user_contract(contract_id);

        CREATE TABLE marts.bridge_user_region (
            user_id   BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            region_id INT    NOT NULL REFERENCES marts.dim_region(region_id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, region_id)
        );

        CREATE TABLE marts.bridge_user_city (
            user_id BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            city_id BIGINT NOT NULL REFERENCES marts.dim_city(city_id)   ON DELETE CASCADE,
            PRIMARY KEY (user_id, city_id)
        );

        CREATE TABLE marts.bridge_user_asset_category (
            user_id           BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            asset_category_id BIGINT NOT NULL REFERENCES marts.dim_asset_category(asset_category_id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, asset_category_id)
        );

        CREATE TABLE marts.bridge_user_property (
            user_id     BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            property_id BIGINT NOT NULL REFERENCES marts.dim_property(property_id) ON DELETE CASCADE,
            PRIMARY KEY (user_id, property_id)
        );

        CREATE TABLE marts.bridge_user_warehouse (
            user_id      BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            warehouse_id BIGINT NOT NULL,
            PRIMARY KEY (user_id, warehouse_id)
        );

        CREATE TABLE marts.bridge_user_beneficiary (
            user_id        BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            beneficiary_id BIGINT NOT NULL,
            PRIMARY KEY (user_id, beneficiary_id)
        );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS marts.bridge_user_beneficiary    CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_warehouse      CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_property       CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_asset_category CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_city           CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_region         CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_contract       CASCADE;
        DROP TABLE IF EXISTS marts.bridge_user_building       CASCADE;
        SQL);
    }
};

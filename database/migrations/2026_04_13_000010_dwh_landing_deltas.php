<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        ALTER TABLE marts.dim_priority
            ADD COLUMN owner_user_id BIGINT REFERENCES marts.dim_user(user_id),
            ADD COLUMN is_deleted    BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN deleted_at    TIMESTAMPTZ,
            ADD COLUMN created_at    TIMESTAMPTZ,
            ADD COLUMN modified_at   TIMESTAMPTZ;
        CREATE INDEX ix_dim_priority_owner ON marts.dim_priority(owner_user_id);

        ALTER TABLE marts.dim_asset_category
            ADD COLUMN owner_user_id BIGINT REFERENCES marts.dim_user(user_id),
            ADD COLUMN is_deleted    BOOLEAN NOT NULL DEFAULT FALSE,
            ADD COLUMN deleted_at    TIMESTAMPTZ,
            ADD COLUMN created_at    TIMESTAMPTZ,
            ADD COLUMN modified_at   TIMESTAMPTZ;
        CREATE INDEX ix_dim_asset_category_owner ON marts.dim_asset_category(owner_user_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP INDEX IF EXISTS marts.ix_dim_asset_category_owner;
        ALTER TABLE marts.dim_asset_category
            DROP COLUMN IF EXISTS modified_at,
            DROP COLUMN IF EXISTS created_at,
            DROP COLUMN IF EXISTS deleted_at,
            DROP COLUMN IF EXISTS is_deleted,
            DROP COLUMN IF EXISTS owner_user_id;

        DROP INDEX IF EXISTS marts.ix_dim_priority_owner;
        ALTER TABLE marts.dim_priority
            DROP COLUMN IF EXISTS modified_at,
            DROP COLUMN IF EXISTS created_at,
            DROP COLUMN IF EXISTS deleted_at,
            DROP COLUMN IF EXISTS is_deleted,
            DROP COLUMN IF EXISTS owner_user_id;
        SQL);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE dwh.sync_state (
            table_name      TEXT PRIMARY KEY,
            last_cursor     TIMESTAMPTZ,
            last_run_at     TIMESTAMPTZ,
            last_status     TEXT,
            last_error      TEXT,
            rows_upserted   BIGINT NOT NULL DEFAULT 0,
            rows_deleted    BIGINT NOT NULL DEFAULT 0,
            updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE dwh.ingest_batch (
            batch_id        BIGSERIAL PRIMARY KEY,
            table_name      TEXT NOT NULL,
            idempotency_key UUID NOT NULL,
            cursor_from     TIMESTAMPTZ,
            cursor_to       TIMESTAMPTZ,
            accepted        INT  NOT NULL DEFAULT 0,
            upserted        INT  NOT NULL DEFAULT 0,
            deleted         INT  NOT NULL DEFAULT 0,
            invalid         INT  NOT NULL DEFAULT 0,
            status          TEXT NOT NULL,
            received_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
            completed_at    TIMESTAMPTZ,
            UNIQUE (table_name, idempotency_key)
        );
        CREATE INDEX ix_ingest_batch_table ON dwh.ingest_batch(table_name, received_at DESC);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS dwh.ingest_batch CASCADE;
        DROP TABLE IF EXISTS dwh.sync_state   CASCADE;
        SQL);
    }
};

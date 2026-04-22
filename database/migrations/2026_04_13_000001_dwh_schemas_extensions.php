<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE SCHEMA IF NOT EXISTS raw;
        CREATE SCHEMA IF NOT EXISTS marts;
        CREATE SCHEMA IF NOT EXISTS reports;
        CREATE SCHEMA IF NOT EXISTS auth;
        CREATE SCHEMA IF NOT EXISTS pii;
        CREATE SCHEMA IF NOT EXISTS dwh;
        REVOKE ALL ON SCHEMA pii FROM PUBLIC;

        CREATE EXTENSION IF NOT EXISTS citext;
        CREATE EXTENSION IF NOT EXISTS pgcrypto;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP SCHEMA IF EXISTS dwh     CASCADE;
        DROP SCHEMA IF EXISTS pii     CASCADE;
        DROP SCHEMA IF EXISTS auth    CASCADE;
        DROP SCHEMA IF EXISTS reports CASCADE;
        DROP SCHEMA IF EXISTS marts   CASCADE;
        DROP SCHEMA IF EXISTS raw     CASCADE;
        SQL);
    }
};

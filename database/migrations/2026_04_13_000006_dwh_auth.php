<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TABLE auth.identity (
            identity_id     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            user_id         BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE RESTRICT,
            provider        TEXT NOT NULL,
            subject         TEXT NOT NULL,
            email_at_link   CITEXT,
            email_verified  BOOLEAN NOT NULL DEFAULT FALSE,
            linked_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
            last_login_at   TIMESTAMPTZ,
            disabled_at     TIMESTAMPTZ,
            UNIQUE (provider, subject)
        );
        CREATE INDEX ix_auth_identity_user ON auth.identity(user_id);

        CREATE TABLE auth.session (
            session_id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            user_id            BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            identity_id        UUID   NOT NULL REFERENCES auth.identity(identity_id) ON DELETE CASCADE,
            refresh_token_hash BYTEA NOT NULL,
            user_agent         TEXT,
            ip_address         INET,
            issued_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
            expires_at         TIMESTAMPTZ NOT NULL,
            revoked_at         TIMESTAMPTZ,
            last_used_at       TIMESTAMPTZ
        );
        CREATE INDEX ix_auth_session_user    ON auth.session(user_id);
        CREATE INDEX ix_auth_session_expires ON auth.session(expires_at) WHERE revoked_at IS NULL;
        CREATE INDEX ix_auth_session_token   ON auth.session(refresh_token_hash);

        CREATE TABLE auth.login_event (
            event_id     BIGSERIAL,
            user_id      BIGINT REFERENCES marts.dim_user(user_id) ON DELETE SET NULL,
            identity_id  UUID   REFERENCES auth.identity(identity_id) ON DELETE SET NULL,
            event_type   TEXT NOT NULL,
            provider     TEXT,
            ip_address   INET,
            user_agent   TEXT,
            metadata     JSONB NOT NULL DEFAULT '{}'::jsonb,
            occurred_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (event_id, occurred_at)
        ) PARTITION BY RANGE (occurred_at);

        CREATE TABLE auth.login_event_y2026 PARTITION OF auth.login_event
            FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
        CREATE TABLE auth.login_event_y2027 PARTITION OF auth.login_event
            FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');
        CREATE TABLE auth.login_event_y2028 PARTITION OF auth.login_event
            FOR VALUES FROM ('2028-01-01') TO ('2029-01-01');
        CREATE TABLE auth.login_event_y2029 PARTITION OF auth.login_event
            FOR VALUES FROM ('2029-01-01') TO ('2030-01-01');
        CREATE TABLE auth.login_event_y2030 PARTITION OF auth.login_event
            FOR VALUES FROM ('2030-01-01') TO ('2031-01-01');

        CREATE INDEX ix_auth_event_user ON auth.login_event(user_id);
        CREATE INDEX ix_auth_event_when ON auth.login_event(occurred_at DESC);

        CREATE TABLE auth.role (
            role_id     SERIAL PRIMARY KEY,
            role_key    TEXT UNIQUE NOT NULL,
            description TEXT
        );

        CREATE TABLE auth.user_role (
            user_id    BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            role_id    INT    NOT NULL REFERENCES auth.role(role_id)     ON DELETE CASCADE,
            granted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            granted_by BIGINT REFERENCES marts.dim_user(user_id),
            PRIMARY KEY (user_id, role_id)
        );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS auth.user_role    CASCADE;
        DROP TABLE IF EXISTS auth.role         CASCADE;
        DROP TABLE IF EXISTS auth.login_event  CASCADE;
        DROP TABLE IF EXISTS auth.session      CASCADE;
        DROP TABLE IF EXISTS auth.identity     CASCADE;
        SQL);
    }
};

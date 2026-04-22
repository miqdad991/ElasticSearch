<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        CREATE TYPE marts.ejar_status_enum AS ENUM
            ('synced_successfully','pending_sync','failed_sync','not_synced');
        CREATE TYPE marts.lease_type_enum  AS ENUM ('rent','lease');
        CREATE TYPE marts.calendar_enum    AS ENUM ('gregorian','hijri');

        CREATE TABLE marts.dim_tenant (
            tenant_id           BIGINT PRIMARY KEY REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
            tenant_phone_number TEXT,
            tenant_email        CITEXT,
            tenant_cr_id        TEXT,
            loaded_at           TIMESTAMPTZ NOT NULL DEFAULT now()
        );

        CREATE TABLE marts.dim_landlord (
            landlord_id BIGSERIAL PRIMARY KEY,
            name        TEXT NOT NULL,
            phone       TEXT,
            email       CITEXT,
            cr_id       TEXT,
            UNIQUE (name, phone, email)
        );

        CREATE TABLE marts.fact_commercial_contract (
            commercial_contract_id    BIGINT PRIMARY KEY,
            reference_number          TEXT,
            contract_name             TEXT,
            contract_type             marts.lease_type_enum,
            tenant_id                 BIGINT REFERENCES marts.dim_tenant(tenant_id) DEFERRABLE INITIALLY DEFERRED,
            landlord_id               BIGINT REFERENCES marts.dim_landlord(landlord_id),
            property_id               BIGINT REFERENCES marts.dim_property(property_id) ON DELETE SET NULL,
            building_id               BIGINT REFERENCES marts.dim_property_building(building_id) ON DELETE SET NULL,
            unit_id                   BIGINT,
            project_id                INT REFERENCES marts.dim_project(project_id) ON DELETE SET NULL,
            created_by                BIGINT REFERENCES marts.dim_user(user_id),
            ejar_contract_id          TEXT,
            ejar_sync_status          marts.ejar_status_enum NOT NULL DEFAULT 'not_synced',
            calendar_type             marts.calendar_enum    NOT NULL DEFAULT 'gregorian',
            start_date                DATE,
            end_date                  DATE,
            signing_date              DATE,
            payment_date              DATE,
            payment_interval          TEXT,
            amount                    NUMERIC(18,2) NOT NULL DEFAULT 0,
            security_deposit_amount   NUMERIC(18,2) NOT NULL DEFAULT 0,
            late_fees_charge          NUMERIC(18,2) NOT NULL DEFAULT 0,
            brokerage_fee             NUMERIC(18,2) NOT NULL DEFAULT 0,
            retainer_fee              NUMERIC(18,2) NOT NULL DEFAULT 0,
            payment_due               NUMERIC(18,2) NOT NULL DEFAULT 0,
            payment_overdue           NUMERIC(18,2) NOT NULL DEFAULT 0,
            currency                  CHAR(3),
            issuing_office            TEXT,
            lessor_iban_token         TEXT,
            status                    SMALLINT NOT NULL DEFAULT 0,
            is_active                 BOOLEAN GENERATED ALWAYS AS (status = 1) STORED,
            auto_renewal              BOOLEAN NOT NULL DEFAULT FALSE,
            is_unit_applies           BOOLEAN NOT NULL DEFAULT FALSE,
            is_dynamic_rent_applies   BOOLEAN NOT NULL DEFAULT FALSE,
            is_deleted                BOOLEAN NOT NULL DEFAULT FALSE,
            contract_terms_json       JSONB,
            contract_attachments_json JSONB,
            created_at                TIMESTAMPTZ NOT NULL,
            source_updated_at         TIMESTAMPTZ,
            loaded_at                 TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_fcc_tenant   ON marts.fact_commercial_contract(tenant_id);
        CREATE INDEX ix_fcc_property ON marts.fact_commercial_contract(property_id);
        CREATE INDEX ix_fcc_building ON marts.fact_commercial_contract(building_id);
        CREATE INDEX ix_fcc_project  ON marts.fact_commercial_contract(project_id);
        CREATE INDEX ix_fcc_type     ON marts.fact_commercial_contract(contract_type);
        CREATE INDEX ix_fcc_ejar     ON marts.fact_commercial_contract(ejar_sync_status);
        CREATE INDEX ix_fcc_dates    ON marts.fact_commercial_contract(start_date, end_date);

        CREATE TABLE marts.fact_installment (
            installment_id             BIGINT NOT NULL,
            commercial_contract_id     BIGINT NOT NULL REFERENCES marts.fact_commercial_contract(commercial_contract_id) ON DELETE CASCADE,
            payment_ref                TEXT,
            transaction_id             TEXT,
            transaction_date           DATE,
            lessor_id                  BIGINT,
            lessor_name_snapshot       TEXT,
            tenant_id                  BIGINT REFERENCES marts.dim_tenant(tenant_id) DEFERRABLE INITIALLY DEFERRED,
            tenant_name_snapshot       TEXT,
            period_start               DATE,
            period_end                 DATE,
            date_before_due            DATE,
            payment_due_date           DATE NOT NULL,
            original_payment_date      DATE,
            payment_date               DATE,
            amount                     NUMERIC(18,2) NOT NULL DEFAULT 0,
            amount_prepayment          NUMERIC(18,2) NOT NULL DEFAULT 0,
            is_paid                    BOOLEAN NOT NULL DEFAULT FALSE,
            is_prepayment              BOOLEAN NOT NULL DEFAULT FALSE,
            payment_type               TEXT,
            payment_interval           TEXT,
            from_bank_token            TEXT,
            to_bank_token              TEXT,
            from_bank_prepayment_token TEXT,
            to_bank_prepayment_token   TEXT,
            payment_type_prepayment    TEXT,
            notes                      TEXT,
            notes_prepayment           TEXT,
            receipt_ref                TEXT,
            receipt_date               DATE,
            updated_by                 BIGINT REFERENCES marts.dim_user(user_id),
            created_at                 TIMESTAMPTZ NOT NULL,
            source_updated_at          TIMESTAMPTZ,
            loaded_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (installment_id, payment_due_date)
        ) PARTITION BY RANGE (payment_due_date);

        CREATE TABLE marts.fact_installment_y2024 PARTITION OF marts.fact_installment FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');
        CREATE TABLE marts.fact_installment_y2025 PARTITION OF marts.fact_installment FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');
        CREATE TABLE marts.fact_installment_y2026 PARTITION OF marts.fact_installment FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
        CREATE TABLE marts.fact_installment_y2027 PARTITION OF marts.fact_installment FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');
        CREATE TABLE marts.fact_installment_y2028 PARTITION OF marts.fact_installment FOR VALUES FROM ('2028-01-01') TO ('2029-01-01');
        CREATE TABLE marts.fact_installment_y2029 PARTITION OF marts.fact_installment FOR VALUES FROM ('2029-01-01') TO ('2030-01-01');
        CREATE TABLE marts.fact_installment_y2030 PARTITION OF marts.fact_installment FOR VALUES FROM ('2030-01-01') TO ('2031-01-01');

        CREATE INDEX ix_fi_contract     ON marts.fact_installment(commercial_contract_id);
        CREATE INDEX ix_fi_tenant       ON marts.fact_installment(tenant_id);
        CREATE INDEX ix_fi_due_date     ON marts.fact_installment(payment_due_date);
        CREATE INDEX ix_fi_paid         ON marts.fact_installment(is_paid);
        CREATE INDEX ix_fi_overdue_open ON marts.fact_installment(payment_due_date) WHERE NOT is_paid;

        -- Tokenization vault
        CREATE TABLE pii.bank_lookup (
            token         TEXT PRIMARY KEY,
            raw_value     TEXT NOT NULL,
            first_seen_at TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP TABLE IF EXISTS pii.bank_lookup              CASCADE;
        DROP TABLE IF EXISTS marts.fact_installment       CASCADE;
        DROP TABLE IF EXISTS marts.fact_commercial_contract CASCADE;
        DROP TABLE IF EXISTS marts.dim_landlord           CASCADE;
        DROP TABLE IF EXISTS marts.dim_tenant             CASCADE;
        DROP TYPE  IF EXISTS marts.calendar_enum          CASCADE;
        DROP TYPE  IF EXISTS marts.lease_type_enum        CASCADE;
        DROP TYPE  IF EXISTS marts.ejar_status_enum       CASCADE;
        SQL);
    }
};

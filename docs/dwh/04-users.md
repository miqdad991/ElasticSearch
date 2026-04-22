# DWH Dashboard Spec — Users (with SSO auth)

**Status:** draft
**Source system:** Osool MySQL (`osool_bef_normalization`)
**Target system:** Postgres 17 (schema `marts`)
**Load cadence:** 30-min push from source → DWH APIs (for analytics rows). Authentication is **out of band** and uses SSO — see §A.
**Delete policy:** hard delete for analytics rows; SSO identities are kept even if the user is removed from the app (audit).

---

## 1. Dashboard summary

Covered UI:
- `/project-dashboard/users` — users attached to the currently selected project.

Decisions supported: headcount by user type, active vs inactive, deletion trends, onboarding velocity.

Additional requirement (out of the dashboard, but in scope for this doc): the new DWH-backed frontend will authenticate via **single sign-on**, so the schema must capture everything needed to issue sessions and authorize requests. This is modeled as a **separate auth schema**, not mixed with analytics.

---

## 2. UI inventory

### Cards

| Card | Formula |
|---|---|
| Total Users | `COUNT(*)` in scope |
| Active | `COUNT(*) WHERE status = 1` |
| Inactive | `COUNT(*) WHERE status = 0` |
| Deleted | `COUNT(*) WHERE is_deleted = 'yes'` |

### Charts

| Chart | Grain | Metric |
|---|---|---|
| Users per month | `YYYY-MM` of `created_at` | `COUNT(*)` |
| By user type | `user_type.name` (lookup via `user_type.slug`) | `COUNT(*)` |
| By status | Active / Inactive | `COUNT(*)` |

### Filters

`user_type`, `status`, `is_deleted`, `created_at` range.
Scope: `users.id ∈ project_user_ids` (via `user_projects` where `project_id = selected`).

### Table columns

User, email, phone, user type, status, deleted, created, last login.

---

## 3. Source map

### 3.1 Primary — `users`

Analytics-relevant columns only (see §A for auth columns):

| Target | Source | Notes |
|---|---|---|
| `user_id` | `users.id` | PK |
| `email` | `users.email` | also auth identifier |
| `full_name` | `users.name` | |
| `first_name` | `users.first_name` | |
| `last_name` | `users.last_name` | |
| `phone` | `users.phone` | |
| `profile_image_url` | `users.profile_img` | |
| `emp_id` | `users.emp_id` | |
| `user_type_slug` | `users.user_type` | enum (13 values) |
| `project_user_id` | `users.project_user_id` | self-FK, project admin user |
| `sp_admin_id` | `users.sp_admin_id` | service provider admin FK |
| `service_provider` | `users.service_provider` | |
| `country_id` | `users.country_id` | |
| `city_id` | `users.city_id` | |
| `status` | `users.status` | tinyint; 1 = active |
| `is_deleted` | `users.is_deleted` | enum → boolean |
| `deleted_at` | `users.deleted_at` | |
| `created_at` | `users.created_at` | cursor |
| `modified_at` | `users.modified_at` | cursor |
| `last_login_at` | `users.last_login_datetime` | |
| `device_type` | `users.device_type` | enum('android','ios') |
| `preferred_language` | `users.selected_app_langugage` | (yes, typo in source) |
| `sms_language` | `users.langForSms` | |
| `approved_max_amount` | `users.approved_max_amount` | approval limit, numeric |
| `salary` | `users.salary` | |
| `allow_akaunting` | `users.allow_akaunting` | tinyint → boolean |
| `akaunting_vendor_id` | `users.akaunting_vendor_id` | |
| `akaunting_customer_id` | `users.akaunting_customer_id` | |
| `created_by` | `users.created_by` | FK → users.id |

### 3.2 CSV columns — exploded into bridges (see doc #5 for the contracts bridge pattern)

| Source CSV column | Target bridge table | Purpose |
|---|---|---|
| `users.building_ids` | `bridge_user_building` | building-manager scope |
| `users.contract_ids` | `bridge_user_contract` | supervisor scope |
| `users.role_regions` | `bridge_user_region` | regional permissions |
| `users.role_cities` | `bridge_user_city` | regional permissions |
| `users.asset_categories` | `bridge_user_asset_category` | |
| `users.keeper_warehouses` | `bridge_user_warehouse` | |
| `users.properties` | `bridge_user_property` | |
| `users.contracts` | duplicate of `contract_ids` — pick one |
| `users.beneficiary` | `bridge_user_beneficiary` | |

None of these are on the current dashboard — included so the auth layer can resolve permissions without re-parsing CSV at request time.

### 3.3 Lookup — `user_type` (already exists in source)

| Column | Source |
|---|---|
| `slug` | `user_type.slug` (matches `users.user_type` enum) |
| `name` | `user_type.name` (display label) |

### 3.4 Bridge — `user_projects`

Already modeled in doc #1. Required here for the dashboard's project scoping.

---

## 4. Target Postgres schema

### 4.1 Analytics dim (extends doc #1's `dim_user` stub)

```sql
-- REPLACE the stub in doc #1 with this
DROP TABLE IF EXISTS marts.dim_user CASCADE;

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
    user_type_label       TEXT,                        -- human name from user_type.name
    project_user_id       BIGINT,                      -- self-FK resolved below
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
    preferred_language    TEXT,                        -- free text (e.g. 'en', 'ar')
    sms_language          marts.language_enum,
    approved_max_amount   NUMERIC(15,2),
    salary                NUMERIC(10,2),
    allow_akaunting       BOOLEAN NOT NULL DEFAULT FALSE,
    akaunting_vendor_id   BIGINT,
    akaunting_customer_id BIGINT,
    created_by            BIGINT,                      -- FK → dim_user.user_id (deferred)
    loaded_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- self-FK, deferred so a batch can contain referrer + referee
ALTER TABLE marts.dim_user
    ADD CONSTRAINT fk_dim_user_project_user
    FOREIGN KEY (project_user_id) REFERENCES marts.dim_user(user_id)
    DEFERRABLE INITIALLY DEFERRED;

ALTER TABLE marts.dim_user
    ADD CONSTRAINT fk_dim_user_created_by
    FOREIGN KEY (created_by) REFERENCES marts.dim_user(user_id)
    DEFERRABLE INITIALLY DEFERRED;

CREATE INDEX ix_dim_user_type        ON marts.dim_user(user_type);
CREATE INDEX ix_dim_user_project     ON marts.dim_user(project_user_id);
CREATE INDEX ix_dim_user_sp_admin    ON marts.dim_user(sp_admin_id);
CREATE INDEX ix_dim_user_created_at  ON marts.dim_user(created_at);
```

> Requires the `citext` extension: `CREATE EXTENSION IF NOT EXISTS citext;` — case-insensitive email.

### 4.2 Bridge tables (CSV exploded)

Skeleton, repeat the same pattern per CSV column:

```sql
CREATE TABLE marts.bridge_user_building (
    user_id     BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    building_id BIGINT NOT NULL REFERENCES marts.dim_property_building(building_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, building_id)
);
CREATE INDEX ix_bub_building ON marts.bridge_user_building(building_id);

CREATE TABLE marts.bridge_user_contract (
    user_id     BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    contract_id BIGINT NOT NULL,  -- FK to dim_contract added in doc #5
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
    user_id         BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    beneficiary_id  BIGINT NOT NULL,
    PRIMARY KEY (user_id, beneficiary_id)
);
```

### 4.3 Raw landing

```sql
CREATE TABLE raw.users (
    id BIGINT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE raw.user_type (
    slug TEXT PRIMARY KEY, payload JSONB NOT NULL, ingested_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- raw.user_projects already declared in doc #1.
```

---

## 5. ETL transforms

1. **Filter** — drop where `is_deleted='yes'` OR `deleted_at IS NOT NULL`.
2. **Enum → boolean** — `is_deleted`, `allow_akaunting`.
3. **CSV → bridges** — for each of the 8 CSV columns in §3.2:
   - `SELECT unnest(string_to_array(NULLIF(building_ids,''),',')::BIGINT[])` → bridge rows.
   - Fully replace the user's bridge entries on every upsert (delete user's rows, insert new set) inside one transaction.
4. **Email normalization** — lowercased and trimmed before insert (`citext` makes comparisons case-insensitive but we still canonicalize on write).
5. **`user_type_label`** — looked up from `raw.user_type.slug`. Transform resolves it once per batch.
6. **Timestamps → `TIMESTAMPTZ`** (assume UTC).
7. **Drop columns** — `password`, `temp_password`, `otp*`, `forgot_password_time`, `api_token`, `crm_api_token`, `device_token`. **None of these are loaded into the analytics warehouse.** Auth uses SSO — see §A.
8. **Upsert** on `user_id`. Bridge rows replaced as a unit.

---

## 6. Incremental load

### 6.1 Cursor
```
modified_at > last_cursor - 10 minutes
```

### 6.2 Upsert
Standard `ON CONFLICT (user_id) DO UPDATE` on `dim_user`; bridge replacement on the same batch (delete-then-insert within a transaction, keyed by `user_id`).

### 6.3 Hard delete
`DELETE FROM marts.dim_user WHERE user_id = ANY($1)` — bridges cascade.
**Exception:** the SSO `auth.identity` row (see §A) is **not** deleted; it's tombstoned via `auth.identity.disabled_at = now()`. This preserves login-history analytics.

---

## 7. Materialized view

```sql
CREATE MATERIALIZED VIEW reports.mv_user_kpis AS
SELECT
    up.project_id,
    u.user_type,
    u.user_type_label,
    to_char(u.created_at, 'YYYY-MM') AS year_month,
    COUNT(*)                                         AS user_count,
    COUNT(*) FILTER (WHERE u.is_active)              AS active_count,
    COUNT(*) FILTER (WHERE NOT u.is_active)          AS inactive_count,
    COUNT(*) FILTER (WHERE u.is_deleted)             AS deleted_count
FROM marts.dim_user u
JOIN marts.bridge_user_project up ON up.user_id = u.user_id
GROUP BY CUBE(up.project_id, u.user_type, u.user_type_label, to_char(u.created_at, 'YYYY-MM'));

CREATE UNIQUE INDEX ix_mv_user_kpis
    ON reports.mv_user_kpis(project_id, user_type, user_type_label, year_month);
```

---

## 8. API contract (analytics side)

### 8.1 Endpoints

```
POST /api/dwh/ingest/user-types
POST /api/dwh/ingest/users
POST /api/dwh/ingest/user-projects
```

Call order: user-types → users → user-projects.

### 8.2 User row (analytics payload — NO passwords, NO tokens)

```json
{
  "id": 45,
  "email": "alex@example.com",
  "name": "Alex Rahman",
  "first_name": "Alex",
  "last_name": "Rahman",
  "phone": "+966500000000",
  "profile_img": "https://cdn.example.com/u/45.jpg",
  "emp_id": "E-0045",
  "user_type": "admin",
  "project_user_id": 45,
  "sp_admin_id": null,
  "service_provider": null,
  "country_id": 1,
  "city_id": 12,
  "status": 1,
  "is_deleted": "no",
  "deleted_at": null,
  "last_login_datetime": "2026-04-12T08:15:00Z",
  "device_type": "ios",
  "selected_app_langugage": "en",
  "langForSms": "English",
  "approved_max_amount": 50000,
  "salary": null,
  "allow_akaunting": 1,
  "akaunting_vendor_id": null,
  "akaunting_customer_id": null,
  "created_by": 1,

  "building_ids":      [101, 102, 103],
  "contract_ids":      [55, 78],
  "role_regions":      [1, 2],
  "role_cities":       [12, 13, 14],
  "asset_categories":  [7, 8],
  "keeper_warehouses": [9],
  "properties":        [2001, 2002],
  "beneficiary":       [],

  "created_at": "2022-05-10T09:00:00Z",
  "modified_at": "2026-04-12T08:15:00Z"
}
```

The CSV columns are pre-parsed to arrays by the source before calling — DWH does not re-split.

### 8.3 User-type row

```json
{ "slug": "admin", "name": "Administrator" }
```

### 8.4 user-projects row

```json
{ "user_id": 45, "project_id": 67 }
```

---

## A. Authentication schema (SSO)

Authentication lives in its own schema — **never** co-mingled with the analytics payload, because credentials have completely different access requirements.

### A.1 Approach

- **Protocol:** OpenID Connect (OAuth 2.1). Supports Google / Microsoft / Apple + an enterprise IdP (Keycloak or Auth0 or Azure AD) as your corporate provider.
- **No passwords in the DWH.** Source passwords are never loaded. The DWH app trusts the IdP's ID token.
- **Multi-IdP** supported via one row per `(user_id, provider)` in `auth.identity`.
- **Sessions** are opaque refresh tokens stored server-side; access tokens are short-lived JWTs.

### A.2 Schema

```sql
CREATE SCHEMA IF NOT EXISTS auth;
CREATE EXTENSION IF NOT EXISTS pgcrypto;    -- for gen_random_uuid()

-- An identity = one login credential at one IdP
CREATE TABLE auth.identity (
    identity_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id           BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE RESTRICT,
    provider          TEXT NOT NULL,            -- 'google','microsoft','apple','keycloak','azuread'
    subject           TEXT NOT NULL,            -- the 'sub' claim from the IdP — stable per IdP
    email_at_link     CITEXT,                   -- email claimed at link time
    email_verified    BOOLEAN NOT NULL DEFAULT FALSE,
    linked_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_login_at     TIMESTAMPTZ,
    disabled_at       TIMESTAMPTZ,              -- tombstone; keeps history without permitting login
    UNIQUE (provider, subject)
);
CREATE INDEX ix_auth_identity_user ON auth.identity(user_id);

-- Server-side session (refresh token)
CREATE TABLE auth.session (
    session_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id           BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    identity_id       UUID   NOT NULL REFERENCES auth.identity(identity_id) ON DELETE CASCADE,
    refresh_token_hash BYTEA NOT NULL,          -- SHA-256 of the opaque token
    user_agent        TEXT,
    ip_address        INET,
    issued_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at        TIMESTAMPTZ NOT NULL,
    revoked_at        TIMESTAMPTZ,
    last_used_at      TIMESTAMPTZ
);
CREATE INDEX ix_auth_session_user      ON auth.session(user_id);
CREATE INDEX ix_auth_session_expires   ON auth.session(expires_at) WHERE revoked_at IS NULL;
CREATE INDEX ix_auth_session_token     ON auth.session(refresh_token_hash);

-- Audit log
CREATE TABLE auth.login_event (
    event_id          BIGSERIAL PRIMARY KEY,
    user_id           BIGINT REFERENCES marts.dim_user(user_id) ON DELETE SET NULL,
    identity_id       UUID   REFERENCES auth.identity(identity_id) ON DELETE SET NULL,
    event_type        TEXT   NOT NULL,        -- 'login','logout','refresh','failed_login','linked','unlinked'
    provider          TEXT,
    ip_address        INET,
    user_agent        TEXT,
    metadata          JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at       TIMESTAMPTZ NOT NULL DEFAULT now()
) PARTITION BY RANGE (occurred_at);

CREATE TABLE auth.login_event_y2026 PARTITION OF auth.login_event
    FOR VALUES FROM ('2026-01-01') TO ('2027-01-01');
CREATE TABLE auth.login_event_y2027 PARTITION OF auth.login_event
    FOR VALUES FROM ('2027-01-01') TO ('2028-01-01');

CREATE INDEX ix_auth_event_user   ON auth.login_event(user_id);
CREATE INDEX ix_auth_event_when   ON auth.login_event(occurred_at DESC);

-- Roles & privileges for the new frontend
CREATE TABLE auth.role (
    role_id      SERIAL PRIMARY KEY,
    role_key     TEXT UNIQUE NOT NULL,        -- 'admin','viewer','sp_admin', etc.
    description  TEXT
);

CREATE TABLE auth.user_role (
    user_id BIGINT NOT NULL REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    role_id INT    NOT NULL REFERENCES auth.role(role_id)       ON DELETE CASCADE,
    granted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    granted_by BIGINT REFERENCES marts.dim_user(user_id),
    PRIMARY KEY (user_id, role_id)
);
```

### A.3 Ingest rules

- `auth.identity` is written **only** by the DWH-app at login/linking time, never by the 30-min sync.
- On first SSO login, the DWH app looks up `marts.dim_user` by the IdP's `email` claim (case-insensitive via `citext`). Match → create `identity` + `session`. No match → reject (user must exist in the 30-min analytics load first).
- On user delete (from the analytics sync): `ON DELETE RESTRICT` on `auth.identity.user_id` blocks the delete; DWH runs a cleanup path that:
  1. Disables all identities: `UPDATE auth.identity SET disabled_at = now() WHERE user_id = :id`
  2. Revokes all sessions: `UPDATE auth.session SET revoked_at = now() WHERE user_id = :id`
  3. Then deletes from `dim_user`. (Identity row survives for audit under the tombstone.)

Actually — to keep the 30-min loader simple, swap the cascade rule:
```sql
ALTER TABLE auth.identity
    DROP CONSTRAINT identity_user_id_fkey,
    ADD CONSTRAINT identity_user_id_fkey
    FOREIGN KEY (user_id) REFERENCES marts.dim_user(user_id) ON DELETE SET NULL;
```
Deletes leave orphaned identities with `user_id = NULL` — they can never log in (FK lookup fails) but stay available for audit. Pick whichever model fits your retention policy; the doc above shows `RESTRICT` as the safer default.

### A.4 Why not store hashed passwords too?

SSO-only is cleaner: no password rotation, no credential-stuffing surface, no expiry reminders. The source system's password columns (`password`, `temp_password`, OTP fields) remain in MySQL and are **not** replicated.

If you later need break-glass local auth, add:
```sql
CREATE TABLE auth.local_credential (
    user_id        BIGINT PRIMARY KEY REFERENCES marts.dim_user(user_id) ON DELETE CASCADE,
    password_hash  TEXT NOT NULL,          -- Argon2id
    password_set_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    must_change    BOOLEAN NOT NULL DEFAULT FALSE
);
```
Until then, don't.

### A.5 API endpoints for the new frontend

```
GET  /auth/oidc/{provider}/start            -- redirects to IdP
GET  /auth/oidc/{provider}/callback         -- exchanges code, creates session, sets refresh cookie
POST /auth/token/refresh                    -- rotates refresh token, returns new access JWT
POST /auth/logout                           -- revokes session
GET  /auth/me                               -- returns user + roles + bridges
```

Access JWT claims:
```json
{
  "sub": "45",                       // dim_user.user_id
  "user_type": "admin",
  "project_user_id": 45,
  "projects": [67, 68],              // from bridge_user_project
  "roles": ["admin","finance_viewer"],
  "iat": 1712990400,
  "exp": 1712994000                  // 1 hour
}
```

---

## 9. Validation checks

```yaml
models:
  - name: dim_user
    columns:
      - name: user_id
        tests: [not_null, unique]
      - name: email
        tests: [not_null, unique]
      - name: user_type
        tests:
          - accepted_values:
              values: [super_admin,osool_admin,admin,admin_employee,building_manager,building_manager_employee,sp_admin,supervisor,sp_worker,tenant,procurement_admin,manual_custodian,team_leader]
      - name: project_user_id
        tests:
          - relationships: { to: ref('dim_user'), field: user_id, severity: warn }
```

Auth-side alerts (`auth.*`):
- Identities with NULL `user_id` growing > X/day → data quality issue.
- Failed-login rate > threshold per IdP → security alert.
- Sessions active > 30 days not yet expired → audit finding.

---

## 10. Open questions

1. Which IdP is the **primary** — Google Workspace, Microsoft 365, or an in-house Keycloak? This affects which `provider` values we seed.
2. Email as the SSO join key is fragile (emails change). Should we also capture a stable external id (e.g. Entra ID `oid`) via `identity.subject`? **Yes — already in the schema.**
3. Soft-delete vs hard-delete for `dim_user` — the project policy says hard delete, but **auth has a harder requirement** (GDPR / audit). Chose `ON DELETE RESTRICT` + tombstone in A.3. Confirm.
4. `users.password` etc. — confirm these will **never** be exposed to the DWH API. The API schema in §8 omits them explicitly.
5. CSV columns (`building_ids`, etc.) — confirm the source will pre-parse to arrays in the JSON payload. Otherwise we re-split in DWH transforms.
6. `users.selected_app_langugage` (source typo) stays or is renamed `preferred_language` in the API contract? Currently renamed in DWH but the payload key stays as the source name.
7. Multiple source rows may share an email in pathological cases (data quality issue). Decide: reject on load, merge, or allow duplicates (can't — `citext UNIQUE`). Current rule: reject, log to DLQ.
8. For the login-event partition cron, who owns it? (Same ops process as `fact_work_order` partitions.)

---

## File location

Saved as `docs/dwh/04-users.md`. Next: `05-billing.md` (covers commercial contracts, payment_details, receivables).

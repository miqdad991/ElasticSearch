# 30-Minute Sync Roadmap — Osool-B2G → DWH

**Goal:** pull incremental data from Osool-B2G (Laravel + MySQL, production) into this DWH project every 30 minutes, with minimal load on the source and a secure channel.

**Status:** draft. Replace this status line and add dates as each phase ships.

---

## Architecture at a glance

```
┌────────────────────┐    HTTPS + HMAC      ┌─────────────────────┐
│  Osool-B2G         │ ◄──────────────────  │  DWH (this project) │
│  (Laravel + MySQL) │      pull /api/dwh   │  Laravel + Postgres │
│                    │  ──────────────────► │  + OpenSearch       │
│  read-only API     │     JSON + cursor    │  scheduler + queues │
└────────────────────┘                      └─────────────────────┘
                                                     │
                                                     ▼
                                       REFRESH MVs + os:reindex
```

**Direction:** DWH pulls. Source exposes a thin read-only API. All cursor logic, retries, ETL, and queueing live in this project. Minimal blast radius on production.

---

## Phase 1 — Source-side read API (in Osool-B2G)

Single generic controller, one route per table (35+ tables — but one shared base class).

### Endpoint shape

```
GET /api/dwh/<resource>?since=<iso8601>&cursor_id=<int>&limit=500
Authorization: HMAC <signature>
X-Timestamp: 1713100123
```

Returns:
```json
{
  "rows":        [ { ... }, { ... } ],
  "deleted_ids": [ 123, 456 ],
  "next":        { "since": "...", "cursor_id": 12900 },
  "has_more":    true
}
```

### Design rules

1. **Keyset pagination** — `WHERE modified_at > :since OR (modified_at = :since AND id > :cursor_id) ORDER BY modified_at, id LIMIT 500`.  No `OFFSET`. No `COUNT(*)`.
2. **Composite index per table** — `ADD INDEX idx_dwh_cursor (modified_at, id)`. One-time migration.
3. **Read-only DB user** for this controller.
4. **Read replica** (recommended) — API points at MySQL replica so sync never hits primary.
5. **Deletes** — either (a) soft-delete via existing `deleted_at` columns (shows up as modified rows), or (b) a `dwh_delete_log` table populated by model events, read as `deleted_ids`.
6. **Sensitive fields stripped at serialization time** (users: no `password`, `temp_password`, `otp*`, tokens. commercial_contracts: no `lessor_iban`, bank accounts.).
7. **Source-column names verbatim**, including typos (`maintanance_request_id`, `calender_type`, `selected_app_langugage`, `langForSms`).

### Table list

Source for each is `docs/api/source-tables-per-dashboard.md` — 35+ tables across 6 dependency stages.

---

## Phase 2 — Security

### Choice (recommended): HMAC + timestamp + IP allowlist

- DWH shares a per-environment secret with Osool (in env vars only, never in code).
- Each request: sign `HMAC-SHA256(secret, timestamp + "\n" + path + "\n" + sha256(body))`.
- Send as `Authorization: HMAC <signature>` + `X-Timestamp: <unix>`.
- Source rejects if `|now - timestamp| > 60s` (prevents replay).
- Source whitelists DWH's outbound IP(s).
- TLS (HTTPS) mandatory, even internal.

### Other layers

- Rate limiting on source: 60 req/min per IP.
- No PII in URLs or logs.
- `X-Idempotency-Key` on every request.

### Alternatives considered

- **mTLS** — cleanest, but cert management overhead.
- **OAuth2 client-credentials** — overkill for server-to-server between two apps you own.

---

## Phase 3 — DWH-side sync engine (this project)

Reusable for every table.

### Components

1. **`dwh.sync_state` table** — already migrated. Tracks `last_cursor`, `last_cursor_id`, `last_run_at`, `last_status` per resource.
2. **`SyncClient` service** — signs + sends requests, handles retries with exponential backoff, parses the envelope.
3. **`TableSyncJob` (queued, one per table)** — paginates until `has_more=false`, upserts into `raw.<table>` as JSONB, then transforms into `marts.*`. Updates `sync_state` atomically at the end.
4. **Scheduler** — Laravel `schedule()` entry every 30 min. Dispatches jobs in dependency-stage order (stage N+1 only after N succeeds).
5. **DLQ** — failed rows go to `raw.<table>_dlq` with the error; alert when it grows.
6. **MV refresh step** — `REFRESH MATERIALIZED VIEW CONCURRENTLY reports.*` after all tables land.
7. **OpenSearch reindex step** — last step calls `os:reindex` for each entity (incremental mode `--since=:last_cursor`).

### Dependency stages (ingest order per cycle)

1. `user_type`, `contract_types`, `contract_payroll_types`, `asset_names`, `asset_statuses`, `regions`, `packages`
2. `cities`, `service_providers`, `users`, `projects_details`
3. `user_projects`, `service_providers_project_mapping`, `properties`
4. `property_buildings`, `asset_categories`, `priorities`
5. `commercial_contracts`, `contracts`, `assets`
6. Everything else (facts, junctions, payrolls, docs, KPIs, `work_orders`, `payment_details`, `lease_contract_details`, `contract_months`, `mapping_osool_akaunting`)

---

## Phase 4 — Observability

- **`/admin/sync-status` page** — one row per table: last cursor, last run, duration, rows upserted/deleted, last error.
- **Alerts** when:
  - cursor age > 60 min (two cycles missed)
  - DLQ row count > 100
  - HTTP error rate > 5% in a window
  - row count diverges > 0.5% from source (daily reconciliation check)
- **Structured JSON logs** per cycle with `cycle_id`, `table`, `rows_in`, `rows_upserted`, `rows_deleted`, `duration_ms`.

---

## Phase 5 — Backfill (one-time, before scheduler goes live)

Per table, pick one:

1. **CSV dump + COPY** — Osool team dumps `SELECT * FROM <table>` to a file, DWH loads with `\copy` / `LOAD DATA`. Best for tables > 1M rows (`work_orders`, `payment_details`).
2. **Let incremental run with `since=NULL`** — walks the whole table via keyset pages. Uses same code path as steady-state. Best for small/medium tables.

---

## Phase 6 — Cutover

1. Deploy Osool read API to **staging**.
2. Backfill DWH in staging.
3. Run 30-min scheduler in staging for ≥ 3 days. Watch metrics.
4. Deploy Osool read API to **production**.
5. Backfill DWH in prod (off-hours if possible).
6. Enable 30-min scheduler in prod.
7. Monitor for 1 week. Tune page size / overlap / concurrency.

---

## Execution order (what to build first)

1. **One-table slice.** Pick `work_orders`. Build the source endpoint + DWH sync job + scheduler entry + MV refresh + OS reindex. End-to-end.
2. **Security.** HMAC signing + IP allowlist + timestamp check.
3. **Backfill strategy** for the slice table.
4. **Observability page** + alerts.
5. **Replicate** the pattern for the remaining 34 tables in dependency-stage order. Each is ~10 lines of config once the base exists.

---

## Open questions (fill in as decided)

1. Can Osool-B2G ship code changes (new endpoints + HMAC middleware + cursor indexes)?
2. Network path — same VPC, VPN, or public internet with allowlist?
3. Is a MySQL read replica available?
4. Rough row counts for `work_orders`, `payment_details`, `assets`?
5. Alerting channel — Slack, email, PagerDuty?
6. SLO for freshness — is 30 min OK, or stricter?

---

## Reference docs

- `docs/dwh/01..08` — target DWH schema per dashboard.
- `docs/api/source-tables-per-dashboard.md` — every source table, join, filter, column used.
- This file — the operational roadmap.

---

## File location

`docs/roadmap/30min-sync-roadmap.md`

<?php

namespace App\Services\Sync;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pulls one resource from Osool-B2G and upserts into raw.<table>.
 *
 * Keeps cursor state in dwh.sync_state.  The ETL step (raw → marts.*)
 * is a separate concern; this layer just lands the JSON payload.
 */
class TableSync
{
    /** @var callable|null */
    public $onProgress = null;

    public function __construct(private OsoolClient $client) {}

    private function emit(string $msg): void
    {
        if ($this->onProgress) ($this->onProgress)($msg);
    }

    /**
     * @param  array  $opts  ['mode' => 'incremental'|'snapshot', 'pk' => 'id', 'composite_pk' => ['user_id','project_id']]
     * @return array{pages:int, rows:int, deleted:int, next_cursor:?string}
     */
    public function run(string $resource, string $rawTable, array $opts = []): array
    {
        $mode        = $opts['mode']         ?? 'incremental';
        $pk          = $opts['pk']           ?? 'id';
        $compositePk = $opts['composite_pk'] ?? null;

        if ($mode === 'snapshot') {
            return $this->runSnapshot($resource, $rawTable, $compositePk);
        }

        // Disable query logging — accumulates across pages and leaks memory on big backfills.
        DB::connection()->disableQueryLog();

        $state = DB::table('dwh.sync_state')->where('table_name', $rawTable)->first();
        $since = $state?->last_cursor;
        $overlap = (int) config('osool.cursor_overlap_seconds', 600);

        if ($since && $overlap > 0) {
            $since = Carbon::parse($since)->subSeconds($overlap)->toIso8601String();
        }

        $cursorId  = 0;
        $totalRows = 0;
        $totalDel  = 0;
        $pages     = 0;
        $lastIso   = $since;

        do {
            $query = array_filter([
                'since'     => $since,
                'cursor_id' => $cursorId ?: null,
                'limit'     => (int) config('osool.page_size', 500),
            ], fn ($v) => $v !== null);

            $t0 = microtime(true);
            $resp = $this->client->get("/api/dwh/{$resource}", $query);
            $pages++;

            $rows       = (array) ($resp['rows']        ?? []);
            $deletedIds = (array) ($resp['deleted_ids'] ?? []);
            $next       = $resp['next']                 ?? null;
            $hasMore    = (bool) ($resp['has_more']     ?? false);

            if ($rows) {
                $payload = [];
                foreach ($rows as $r) {
                    $payload[] = [
                        'id'          => $r[$pk] ?? null,
                        'payload'     => json_encode($r, JSON_UNESCAPED_UNICODE),
                        'ingested_at' => now(),
                    ];
                }
                DB::table("raw.{$rawTable}")->upsert($payload, ['id'], ['payload', 'ingested_at']);
                $totalRows += count($payload);
            }

            if ($deletedIds) {
                DB::table("raw.{$rawTable}")->whereIn('id', $deletedIds)->delete();
                $totalDel += count($deletedIds);
            }

            $this->emit(sprintf('    page %d: +%d rows, %d deleted (%.2fs, total=%d, mem=%dMB)',
                $pages, count($rows), count($deletedIds), microtime(true) - $t0, $totalRows,
                (int) (memory_get_usage(true) / 1048576)));

            // Release page-scoped references so GC can reclaim memory.
            unset($resp, $rows, $deletedIds, $payload);
            if ($pages % 20 === 0) gc_collect_cycles();

            if ($next) {
                $since    = $next['since']     ?? $since;
                $cursorId = (int) ($next['cursor_id'] ?? 0);
                $lastIso  = $next['since']     ?? $lastIso;
            }
        } while ($hasMore);

        // Persist cursor + counters. Split insert/update so the raw
        // "COALESCE(col, 0) + N" expression only runs in UPDATE.
        $exists = DB::table('dwh.sync_state')->where('table_name', $rawTable)->exists();
        if ($exists) {
            DB::table('dwh.sync_state')
                ->where('table_name', $rawTable)
                ->update([
                    'last_cursor'    => $lastIso,
                    'last_run_at'    => now(),
                    'last_status'    => 'ok',
                    'last_error'     => null,
                    'rows_upserted'  => DB::raw("COALESCE(rows_upserted, 0) + {$totalRows}"),
                    'rows_deleted'   => DB::raw("COALESCE(rows_deleted, 0) + {$totalDel}"),
                    'updated_at'     => now(),
                ]);
        } else {
            DB::table('dwh.sync_state')->insert([
                'table_name'    => $rawTable,
                'last_cursor'   => $lastIso,
                'last_run_at'   => now(),
                'last_status'   => 'ok',
                'last_error'    => null,
                'rows_upserted' => $totalRows,
                'rows_deleted'  => $totalDel,
                'updated_at'    => now(),
            ]);
        }

        return [
            'pages'       => $pages,
            'rows'        => $totalRows,
            'deleted'     => $totalDel,
            'next_cursor' => $lastIso,
        ];
    }

    /**
     * Snapshot mode: TRUNCATE raw.<table> then load all rows in pages.
     * For composite-key bridge tables (e.g. user_projects).
     */
    private function runSnapshot(string $resource, string $rawTable, ?array $compositePk): array
    {
        // No compositePk → standard id+payload landing (raw table has 'id' + 'payload').
        DB::table("raw.{$rawTable}")->truncate();

        $offset    = 0;
        $totalRows = 0;
        $pages     = 0;

        do {
            $t0 = microtime(true);
            $resp = $this->client->get("/api/dwh/{$resource}", [
                'limit'  => 2000,
                'offset' => $offset,
            ]);
            $pages++;
            $this->emit(sprintf('    page %d (offset=%d, %.2fs)', $pages, $offset, microtime(true) - $t0));

            $rows    = (array) ($resp['rows']     ?? []);
            $hasMore = (bool)  ($resp['has_more'] ?? false);

            if ($rows) {
                $payload = [];
                foreach ($rows as $r) {
                    if ($compositePk) {
                        $row = ['ingested_at' => now()];
                        foreach ($compositePk as $col) $row[$col] = $r[$col] ?? null;
                    } else {
                        $row = [
                            'id'          => $r['id'] ?? null,
                            'payload'     => json_encode($r, JSON_UNESCAPED_UNICODE),
                            'ingested_at' => now(),
                        ];
                    }
                    $payload[] = $row;
                }
                DB::table("raw.{$rawTable}")->insertOrIgnore($payload);
                $totalRows += count($payload);
            }

            $offset = $resp['next']['offset'] ?? ($offset + 2000);
        } while ($hasMore);

        DB::table('dwh.sync_state')->updateOrInsert(
            ['table_name' => $rawTable],
            [
                'last_cursor' => null,
                'last_run_at' => now(),
                'last_status' => 'ok',
                'last_error'  => null,
                'updated_at'  => now(),
            ]
        );

        return ['pages' => $pages, 'rows' => $totalRows, 'deleted' => 0, 'next_cursor' => null];
    }
}

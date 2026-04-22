<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenSearch\Client;

class DwhWipe extends Command
{
    protected $signature   = 'dwh:wipe {--force : Skip confirmation}';
    protected $description = 'Truncate all DWH tables and delete OpenSearch indices — prepare for real data.';

    public function handle(Client $os): int
    {
        if (!$this->option('force') && !$this->confirm('This will wipe ALL DWH data (Postgres marts/raw/auth/pii/reports/dwh + OpenSearch osool_* indices). Continue?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->info('Truncating Postgres tables…');

        // Collect every table in the DWH schemas we want to wipe.
        $schemas = ['raw', 'marts', 'auth', 'pii', 'dwh'];
        $tables = DB::table('information_schema.tables')
            ->select('table_schema', 'table_name')
            ->whereIn('table_schema', $schemas)
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_schema')
            ->orderBy('table_name')
            ->get();

        if ($tables->isEmpty()) {
            $this->warn('No DWH tables found.');
            return self::SUCCESS;
        }

        $qualified = $tables->map(fn ($t) => "\"{$t->table_schema}\".\"{$t->table_name}\"")->implode(', ');

        DB::unprepared("TRUNCATE {$qualified} RESTART IDENTITY CASCADE;");
        $this->line("  · truncated {$tables->count()} tables.");

        // Refresh the MVs so they're empty (not left with cached counts)
        $this->info('Refreshing materialized views…');
        $mvs = DB::select("SELECT schemaname, matviewname FROM pg_matviews WHERE schemaname = 'reports'");
        foreach ($mvs as $mv) {
            try {
                DB::statement("REFRESH MATERIALIZED VIEW \"{$mv->schemaname}\".\"{$mv->matviewname}\"");
                $this->line("  · refreshed {$mv->schemaname}.{$mv->matviewname}");
            } catch (\Throwable $e) {
                $this->warn("  · skip {$mv->matviewname}: " . $e->getMessage());
            }
        }

        // Delete OpenSearch indices matching the prefix
        $this->info('Deleting OpenSearch indices…');
        $prefix  = config('opensearch.index_prefix', 'osool_');
        $pattern = $prefix . '*';

        try {
            $indices = $os->indices()->get(['index' => $pattern]);
            $names   = array_keys($indices);
            if (empty($names)) {
                $this->line('  · no matching indices found.');
            } else {
                foreach ($names as $name) {
                    $os->indices()->delete(['index' => $name]);
                    $this->line("  · deleted {$name}");
                }
            }
        } catch (\Throwable $e) {
            $this->warn('  · OpenSearch cleanup skipped: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('✔ DWH wiped. Postgres tables are empty, MVs refreshed, OS indices deleted.');
        $this->warn('Note: `marts.dim_date` has also been truncated. Reseed it before loading data if needed.');
        return self::SUCCESS;
    }
}

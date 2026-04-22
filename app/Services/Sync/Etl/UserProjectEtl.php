<?php

namespace App\Services\Sync\Etl;

use Illuminate\Support\Facades\DB;

/**
 * Snapshot bridge: replace marts.bridge_user_project to mirror raw.user_projects.
 * Skips rows that would orphan FKs (user/project not yet in marts).
 */
class UserProjectEtl implements TableEtl
{
    public function transform(): array
    {
        DB::statement('TRUNCATE marts.bridge_user_project');

        $inserted = DB::statement(<<<'SQL'
            INSERT INTO marts.bridge_user_project (user_id, project_id)
            SELECT DISTINCT up.user_id, up.project_id
            FROM raw.user_projects up
            JOIN marts.dim_user    u ON u.user_id    = up.user_id
            JOIN marts.dim_project p ON p.project_id = up.project_id
            ON CONFLICT DO NOTHING
        SQL);

        $count = DB::table('marts.bridge_user_project')->count();
        return ['upserted' => $count, 'deleted' => 0];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use OpenSearch\Client;

class ProjectSelectController extends Controller
{
    public function __construct(private Client $os) {}

    public function index(Request $request)
    {
        $index   = config('opensearch.index_prefix', 'osool_') . 'projects';
        $perPage = 12;
        $page    = max(1, (int) $request->query('page', 1));
        $search  = trim((string) $request->query('search', ''));

        $query = $search
            ? ['bool' => ['should' => [
                ['match'    => ['project_name' => ['query' => $search, 'operator' => 'and']]],
                ['wildcard' => ['project_name.raw' => '*' . strtolower($search) . '*']],
            ], 'minimum_should_match' => 1]]
            : ['match_all' => (object) []];

        $resp = $this->os->search([
            'index' => $index,
            'body'  => [
                'from'  => ($page - 1) * $perPage,
                'size'  => $perPage,
                'query' => $query,
                'sort'  => [['project_id' => 'asc']],
            ],
        ]);

        $hits  = collect($resp['hits']['hits'] ?? [])->pluck('_source');
        $total = $resp['hits']['total']['value'] ?? 0;

        Paginator::currentPageResolver(fn () => $page);
        $paginator = new LengthAwarePaginator($hits, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);

        return view('select-project', [
            'projects' => $paginator,
            'search'   => $search,
        ]);
    }

    public function select(Request $request, int $projectId)
    {
        // Verify the project exists in the index
        $index = config('opensearch.index_prefix', 'osool_') . 'projects';
        try {
            $doc = $this->os->get(['index' => $index, 'id' => $projectId])['_source'] ?? null;
        } catch (\Throwable) {
            abort(404);
        }
        if (!$doc) abort(404);

        session([
            'selected_project_id'   => $projectId,
            'selected_project_name' => $doc['project_name'] ?? ("Project #{$projectId}"),
        ]);

        return redirect('/project-dashboard');
    }

    public function exit()
    {
        session()->forget(['selected_project_id', 'selected_project_name']);
        return redirect('/select-project');
    }
}

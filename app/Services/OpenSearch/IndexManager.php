<?php

namespace App\Services\OpenSearch;

use OpenSearch\Client;

class IndexManager
{
    public function __construct(private Client $client) {}

    public function prefix(): string
    {
        return (string) config('opensearch.index_prefix', 'osool_');
    }

    public function aliasName(string $entity): string
    {
        return $this->prefix() . $entity;
    }

    /**
     * Create a new versioned index with the given mapping/settings and return its name.
     * Caller swaps the alias to it after a successful bulk load.
     */
    public function createVersionedIndex(string $entity, array $mappings, array $settings = []): string
    {
        $name = $this->aliasName($entity) . '_' . date('YmdHis');

        $this->client->indices()->create([
            'index' => $name,
            'body'  => [
                'settings' => array_merge([
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 0,
                    'refresh_interval'   => '30s',
                ], $settings),
                'mappings' => $mappings,
            ],
        ]);

        return $name;
    }

    /**
     * Atomically point the alias at $newIndex and drop the previously aliased indices.
     */
    public function swapAlias(string $entity, string $newIndex): void
    {
        $alias    = $this->aliasName($entity);
        $existing = [];

        try {
            $existing = array_keys($this->client->indices()->getAlias(['name' => $alias]));
        } catch (\Throwable) {
            // alias doesn't exist yet
        }

        $actions = [['add' => ['index' => $newIndex, 'alias' => $alias]]];
        foreach ($existing as $old) {
            if ($old !== $newIndex) {
                $actions[] = ['remove' => ['index' => $old, 'alias' => $alias]];
            }
        }

        $this->client->indices()->updateAliases(['body' => ['actions' => $actions]]);

        foreach ($existing as $old) {
            if ($old !== $newIndex) {
                try {
                    $this->client->indices()->delete(['index' => $old]);
                } catch (\Throwable) {
                    // ignore
                }
            }
        }
    }

    public function bulk(array $body): void
    {
        if (empty($body)) {
            return;
        }
        $resp = $this->client->bulk(['body' => $body]);
        if (!empty($resp['errors'])) {
            $first = collect($resp['items'] ?? [])->first(fn ($i) => isset(reset($i)['error']));
            throw new \RuntimeException('Bulk indexing error: ' . json_encode($first, JSON_UNESCAPED_SLASHES));
        }
    }
}

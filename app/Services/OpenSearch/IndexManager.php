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
     * True if the alias for $entity exists and resolves to at least one index.
     * Used by `os:ensure` to detect a missing index and self-heal.
     */
    public function aliasExists(string $entity): bool
    {
        try {
            return !empty($this->client->indices()->getAlias(['name' => $this->aliasName($entity)]));
        } catch (\Throwable) {
            return false; // alias (and its index) don't exist
        }
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

        // Sweep up leftover versioned indices for this entity. A reindex that
        // dies after createVersionedIndex() but before this swap orphans an
        // empty index that the alias never pointed at — so the loop above never
        // deletes it. Left unchecked these accumulate one shard each and can hit
        // cluster.max_shards_per_node, after which new index creation fails.
        $this->pruneOrphans($entity, $newIndex);
    }

    /**
     * Delete versioned indices for $entity that are strictly older than
     * $keepIndex (by their YmdHis timestamp suffix) and not the live one.
     *
     * The age check is deliberate: a concurrent reindex builds a NEWER index
     * (larger timestamp) that may still be mid-bulk, so we must never delete it.
     * Orphans from failed runs are always older than the latest good build.
     *
     * @return string[] names of deleted orphan indices
     */
    public function pruneOrphans(string $entity, string $keepIndex): array
    {
        $prefix  = $this->aliasName($entity) . '_';
        $keepTs  = substr($keepIndex, strlen($prefix));
        $deleted = [];

        try {
            $all = array_keys($this->client->indices()->get(['index' => $prefix . '*']));
        } catch (\Throwable) {
            return $deleted; // nothing matched, or transient API error
        }

        foreach ($all as $name) {
            $ts = substr($name, strlen($prefix));
            if ($name === $keepIndex || $ts === '' || $ts >= $keepTs) {
                continue; // keep the live index and any newer (possibly in-flight) build
            }
            try {
                $this->client->indices()->delete(['index' => $name]);
                $deleted[] = $name;
            } catch (\Throwable) {
                // ignore
            }
        }

        return $deleted;
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

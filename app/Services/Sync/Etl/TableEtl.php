<?php

namespace App\Services\Sync\Etl;

interface TableEtl
{
    /**
     * Transform rows from raw.<table> into marts.<target>.
     * Should be idempotent (re-runnable with no side effects).
     *
     * @return array{upserted:int, deleted:int}
     */
    public function transform(): array;
}

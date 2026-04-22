<?php

$scheme = env('OPENSEARCH_SCHEME', 'http');
$host   = env('OPENSEARCH_HOST', 'localhost');
$port   = env('OPENSEARCH_PORT', 9200);

return [
    'hosts' => ["{$scheme}://{$host}:{$port}"],

    'username' => env('OPENSEARCH_USER', env('OPENSEARCH_USERNAME')),
    'password' => env('OPENSEARCH_PASSWORD'),

    'ssl_verification' => env('OPENSEARCH_SSL_VERIFY', false),
    'retries'          => (int) env('OPENSEARCH_RETRIES', 2),
    'index_prefix'     => env('OPENSEARCH_INDEX_PREFIX', 'osool_'),
];

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenSearch\ClientBuilder;
use OpenSearch\Client;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $cfg = config('opensearch');

            $builder = ClientBuilder::create()
                ->setHosts($cfg['hosts'])
                ->setSSLVerification((bool) $cfg['ssl_verification'])
                ->setRetries($cfg['retries']);

            if (!empty($cfg['username'])) {
                $builder->setBasicAuthentication($cfg['username'], (string) $cfg['password']);
            }

            return $builder->build();
        });
    }

    public function boot(): void
    {
        //
    }
}

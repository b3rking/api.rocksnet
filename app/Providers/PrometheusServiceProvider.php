<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorRegistry::class, function () {
            Redis::setDefaultOptions([
                'host' => config('database.redis.default.host', '127.0.0.1'),
                'port' => config('database.redis.default.port', 6379),
                'password' => config('database.redis.default.password', null),
                'timeout' => 0.1,
                'read_timeout' => 10,
                'persistent_connections' => false,
            ]);

            return new CollectorRegistry(new Redis());
        });
    }
}

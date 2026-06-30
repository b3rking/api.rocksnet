<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;

class PrometheusMiddleware
{
    private Counter $requestsTotal;
    private Histogram $requestDuration;

    public function __construct(CollectorRegistry $registry)
    {
        $this->requestsTotal = $registry->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'status', 'path']
        );

        $this->requestDuration = $registry->getOrRegisterHistogram(
            'app',
            'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'status', 'path'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
    }

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        $labels = [
            $request->method(),
            (string) $response->getStatusCode(),
            $request->path(),
        ];

        $this->requestsTotal->inc($labels);
        $this->requestDuration->observe($duration, $labels);

        return $response;
    }
}

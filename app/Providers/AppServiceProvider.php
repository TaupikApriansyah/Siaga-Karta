<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $threshold = (int) config('siagakarta.performance.slow_query_ms', 0);
        if ($threshold <= 0) return;

        DB::listen(function (QueryExecuted $query) use ($threshold) {
            if ($query->time < $threshold) return;
            Log::warning('Slow database query', [
                'connection' => $query->connectionName,
                'time_ms' => $query->time,
                // Intentionally exclude bindings so credentials/PII are not written to logs.
                'sql' => $query->sql,
            ]);
        });
    }
}

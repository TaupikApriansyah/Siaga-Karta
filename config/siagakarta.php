<?php
return [
    // Keep this secret stable across APP_KEY rotations so searchable fingerprints remain valid.
    'data_fingerprint_key' => env('DATA_FINGERPRINT_KEY') ?: env('APP_KEY'),
    'auth' => [
        'idle_minutes' => (int) env('AUTH_IDLE_MINUTES', 60),
        'absolute_hours' => (int) env('AUTH_ABSOLUTE_HOURS', 12),
        'refresh_warning_minutes' => (int) env('AUTH_REFRESH_WARNING_MINUTES', 5),
        'max_active_tokens' => (int) env('AUTH_MAX_ACTIVE_TOKENS', 8),
    ],
    'map' => [
        'bandung_kelurahan_geojson_url' => env('BANDUNG_KELURAHAN_GEOJSON_URL', 'https://github.com/tryfatur/geojson-bandung/raw/refs/heads/master/3273-kota-bandung-level-kelurahan.json'),
        'region_api_base_url' => rtrim(env('BANDUNG_REGION_API_BASE_URL', 'https://wilayah.id/api'), '/'),
    ],
    'performance' => [
        // 0 disables query profiling. Keep bindings out of logs to avoid leaking PII.
        'slow_query_ms' => (int) env('SLOW_QUERY_MS', 0),
    ],
    'retention' => [
        'read_notifications_days' => (int) env('READ_NOTIFICATION_RETENTION_DAYS', 90),
        'unread_notifications_days' => (int) env('UNREAD_NOTIFICATION_RETENTION_DAYS', 180),
    ],
];

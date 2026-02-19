<?php

declare(strict_types=1);

return [
    'webguard_core_api_key' => env('WEBGUARD_CORE_API_KEY'),
    'webguard_core_api_url' => env('WEBGUARD_CORE_API_URL'),
    'location' => env('WEBGUARD_LOCATION'),
    'http_retry_times' => (int) env('WEBGUARD_HTTP_RETRY_TIMES', 1),
    'http_retry_delay_ms' => (int) env('WEBGUARD_HTTP_RETRY_DELAY_MS', 250),
];

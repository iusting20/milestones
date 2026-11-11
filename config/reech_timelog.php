<?php

/*
| Reech work sessions on the Log page (read-only). Reech exposes
| GET {url}?from=YYYY-MM-DD&to=YYYY-MM-DD with a Bearer token. Leave the URL or
| token empty to turn the feature off.
*/
return [
    'url' => env('REECH_TIMELOG_URL'),
    'token' => env('REECH_TIMELOG_TOKEN'),
    'timeout' => (float) env('REECH_TIMELOG_TIMEOUT', 3),
    'cache_seconds' => (int) env('REECH_TIMELOG_CACHE_SECONDS', 120),
    'failure_cache_seconds' => 60,
    'titles' => [
        'content' => 'Make Products - Content',
        'engagement' => 'Make Products - Engage',
    ],
];

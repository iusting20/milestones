<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Docmost URL
    |--------------------------------------------------------------------------
    |
    | Used to build space and page links. The live host is impactfultech.online
    | (compose APP_URL may still say impactfultech.me).
    |
    */

    'url' => rtrim((string) env('DOCMOST_URL', 'https://impactfultech.online'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Space list and top-level pages are cached for a few minutes.
    |
    */

    'cache_ttl' => (int) env('DOCMOST_CACHE_TTL', 300),

];

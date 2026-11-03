<?php

return [
    // Secret path segment: the tool lives at /impact-maps-{token}. Empty disables it.
    'token' => env('IMPACT_MAPS_TOKEN', ''),
];

<?php

return [
    /*
     | Secret path segment for the job-applications page (/x/{token}). The page is
     | also behind the normal login. Unset = the route is not registered at all.
     */
    'page_token' => env('JOBS_PAGE_TOKEN'),

    /*
     | Bearer token for the JSON API (/api/jobs). Separate from the page token.
     | Unset = the API routes are not registered at all.
     */
    'api_token' => env('JOBS_API_TOKEN'),

    // Requests per minute per client IP for the API.
    'api_rate_limit' => (int) env('JOBS_API_RATE_LIMIT', 60),
];

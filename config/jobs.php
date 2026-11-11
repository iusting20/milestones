<?php

return [
    /*
     | Secret path segment for the job-applications page (/x/{token}). The page is
     | also behind the normal login. Unset = the route is not registered at all.
     */
    'page_token' => env('JOBS_PAGE_TOKEN'),
];

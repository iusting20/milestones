<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer-token auth for the jobs JSON API (JOBS_API_TOKEN), compared in constant time. */
class JobsApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('jobs.api_token');
        $given = (string) $request->bearerToken();

        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Unauthenticated.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }

        return $next($request);
    }
}

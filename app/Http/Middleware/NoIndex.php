<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The whole app is private: tell search engines not to index anything. */
class NoIndex
{
    public const VALUE = 'noindex, nofollow, noarchive';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', self::VALUE);

        return $response;
    }
}

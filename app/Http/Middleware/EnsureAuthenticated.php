<?php

namespace App\Http\Middleware;

use App\Support\RememberLogin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    public const SESSION_KEY = 'login_authenticated';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get(self::SESSION_KEY) !== true && RememberLogin::accepts($request->cookie(RememberLogin::COOKIE))) {
            $request->session()->put(self::SESSION_KEY, true);
        }

        if ($request->routeIs('login', 'login.store')) {
            return $next($request);
        }

        if ($request->session()->get(self::SESSION_KEY) === true) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }
}

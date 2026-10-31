<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class RememberLogin
{
    public const COOKIE = 'remember_login';

    public const MINUTES = 60 * 24 * 400;

    public static function issue(): SymfonyCookie
    {
        return cookie(
            self::COOKIE,
            self::token(),
            self::MINUTES,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(self::COOKIE);
    }

    public static function accepts(?string $token): bool
    {
        if (! is_string($token) || ! str_contains($token, '|')) {
            return false;
        }

        [$expires, $mac] = explode('|', $token, 2);

        if (! ctype_digit($expires) || (int) $expires < now()->getTimestamp()) {
            return false;
        }

        $user = (string) config('login.user');

        if ($user === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $user.'|'.$expires, (string) config('app.key'));

        return hash_equals($expected, $mac);
    }

    private static function token(): string
    {
        $expires = (string) now()->addMinutes(self::MINUTES)->getTimestamp();
        $mac = hash_hmac('sha256', (string) config('login.user').'|'.$expires, (string) config('app.key'));

        return $expires.'|'.$mac;
    }
}

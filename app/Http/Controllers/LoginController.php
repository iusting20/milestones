<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAuthenticated;
use App\Support\RememberLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (session(EnsureAuthenticated::SESSION_KEY) === true) {
            return redirect()->route('milestones');
        }

        return view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = (string) config('login.user');
        $password = (string) config('login.password');

        $userMatches = $user !== '' && hash_equals($user, $credentials['user']);
        $passwordMatches = $password !== '' && hash_equals($password, $credentials['password']);

        if (! $userMatches || ! $passwordMatches) {
            return back()
                ->withInput($request->only('user'))
                ->withErrors(['user' => 'Those credentials do not match.']);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureAuthenticated::SESSION_KEY, true);

        $response = redirect()->intended(route('milestones'));

        if ($request->boolean('remember')) {
            Cookie::queue(RememberLogin::issue());
        }

        return $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Cookie::queue(RememberLogin::forget());

        return redirect()->route('login');
    }
}

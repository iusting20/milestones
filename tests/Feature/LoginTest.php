<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAuthenticated;
use App\Support\RememberLogin;
use Tests\TestCase;

class LoginTest extends TestCase
{
    protected bool $authenticate = false;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/milestones')
            ->assertRedirect(route('login'));
    }

    public function test_login_page_is_public(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Log in');
    }

    public function test_valid_credentials_start_a_session(): void
    {
        config([
            'login.user' => 'milestones@example.com',
            'login.password' => 'correct-horse',
        ]);

        $this->post(route('login.store'), [
            'user' => 'milestones@example.com',
            'password' => 'correct-horse',
        ])->assertRedirect(route('milestones'));

        $this->assertTrue(session(EnsureAuthenticated::SESSION_KEY));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        config([
            'login.user' => 'milestones@example.com',
            'login.password' => 'correct-horse',
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'user' => 'milestones@example.com',
                'password' => 'wrong',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('user');

        $this->assertNotTrue(session(EnsureAuthenticated::SESSION_KEY));
    }

    public function test_remember_cookie_restores_the_session(): void
    {
        config([
            'login.user' => 'milestones@example.com',
            'login.password' => 'correct-horse',
        ]);

        $this->post(route('login.store'), [
            'user' => 'milestones@example.com',
            'password' => 'correct-horse',
            'remember' => '1',
        ])->assertCookie(RememberLogin::COOKIE);

        $this->flushSession();

        $this->withCookie(RememberLogin::COOKIE, RememberLogin::issue()->getValue())
            ->get('/')
            ->assertRedirect('/milestones');
    }

    public function test_logout_forgets_the_remember_cookie(): void
    {
        $this->withSession([
            EnsureAuthenticated::SESSION_KEY => true,
        ])->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertCookieExpired(RememberLogin::COOKIE);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->withSession([
            EnsureAuthenticated::SESSION_KEY => true,
        ])->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->get(route('milestones'))
            ->assertRedirect(route('login'));
    }
}

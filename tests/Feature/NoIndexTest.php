<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_noindex_header(): void
    {
        $this->get('/impact-maps')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->flushSession();
        $this->get('/impact-maps')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get(route('login'))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/impact-maps/'.str_repeat('z', 32))->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_robots_txt_disallows_everything(): void
    {
        $this->assertSame("User-agent: *\nDisallow: /\n", file_get_contents(public_path('robots.txt')));
    }
}

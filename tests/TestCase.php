<?php

namespace Tests;

use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $authenticate = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->authenticate) {
            $this->withSession([
                EnsureAuthenticated::SESSION_KEY => true,
            ]);
        }
    }
}

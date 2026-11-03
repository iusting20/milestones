<?php

namespace Tests\Unit;

use App\Support\WeekLabel;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class WeekLabelTest extends TestCase
{
    public function test_same_month(): void
    {
        $this->assertSame('2-8 Nov', WeekLabel::range(Carbon::parse('2026-11-02')));
    }

    public function test_across_months(): void
    {
        $this->assertSame('30 Nov-6 Dec', WeekLabel::range(Carbon::parse('2026-11-30')));
    }

    public function test_across_years(): void
    {
        $this->assertSame('28 Dec 2026-3 Jan 2027', WeekLabel::range(Carbon::parse('2026-12-28')));
    }
}

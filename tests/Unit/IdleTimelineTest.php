<?php

namespace Tests\Unit;

use App\Support\IdleTimeline;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdleTimelineTest extends TestCase
{
    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-10-30 '.$time, 'Europe/Bucharest');
    }

    private string $after = '2026-10-31 12:00';

    #[Test]
    public function no_logs_means_fully_idle(): void
    {
        $r = IdleTimeline::forDay('2026-10-30', [], Carbon::parse($this->after, 'Europe/Bucharest'));

        $this->assertSame(540, $r['idleMinutes']);
        $this->assertCount(1, $r['segments']);
        $this->assertSame('Idle 08:00–17:00 (9h)', $r['segments'][0]['label']);
        $this->assertNull($r['future']);
        $this->assertCount(10, $r['ticks']);
    }

    #[Test]
    public function overlapping_logs_are_merged_and_clipped(): void
    {
        $r = IdleTimeline::forDay('2026-10-30', [
            [$this->at('07:00'), $this->at('09:00')],
            [$this->at('10:00'), $this->at('11:00')],
            [$this->at('10:30'), $this->at('12:00')],
            [$this->at('16:00'), $this->at('19:00')],
        ], Carbon::parse($this->after, 'Europe/Bucharest'));

        $this->assertSame(['Idle 09:00–10:00 (1h)', 'Idle 12:00–16:00 (4h)'], array_column($r['segments'], 'label'));
        $this->assertSame(300, $r['idleMinutes']);
        $this->assertEqualsWithDelta(11.111, $r['segments'][0]['left'], 0.01);
    }

    #[Test]
    public function today_only_counts_idle_until_now(): void
    {
        $r = IdleTimeline::forDay('2026-10-30', [
            [$this->at('08:00'), $this->at('09:00')],
        ], $this->at('10:15'));

        $this->assertSame(['Idle 09:00–10:15 (1h 15m)'], array_column($r['segments'], 'label'));
        $this->assertSame(75, $r['idleMinutes']);
        $this->assertNotNull($r['future']);
        $this->assertEqualsWithDelta(75.0, $r['future']['width'], 0.01);
    }

    #[Test]
    public function future_day_has_no_idle(): void
    {
        $r = IdleTimeline::forDay('2026-10-30', [], $this->at('07:00'));

        $this->assertSame([], $r['segments']);
        $this->assertSame(0, $r['idleMinutes']);
        $this->assertEqualsWithDelta(100.0, $r['future']['width'], 0.01);
    }
}

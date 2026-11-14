<?php

namespace Tests\Feature;

use App\Models\WalkInOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimezoneBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_laravel_uses_the_philippine_timezone_for_generated_timestamps(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());

        Carbon::setTestNow(Carbon::parse('2026-11-15 00:30:00', 'Asia/Manila'));

        $this->assertSame('2026-11-15T00:30:00+08:00', now()->toIso8601String());
    }

    public function test_utc_timestamp_is_assigned_to_the_philippine_calendar_day(): void
    {
        $timestamp = Carbon::parse('2026-11-14T16:30:00Z')
            ->setTimezone('Asia/Manila');

        $this->assertSame('2026-11-15', $timestamp->toDateString());
        $this->assertSame('00:30', $timestamp->format('H:i'));
    }

    public function test_today_date_filter_uses_philippine_midnight_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 00:45:00', 'Asia/Manila'));

        $beforeMidnight = new WalkInOrder([
            'full_name' => 'Before PHT midnight',
            'gallons' => 1,
            'amount' => 30,
            'payment_method' => 'cash',
        ]);
        $beforeMidnight->created_at = Carbon::parse('2026-11-14 23:59:00', 'Asia/Manila');
        $beforeMidnight->save();

        $afterMidnight = new WalkInOrder([
            'full_name' => 'After PHT midnight',
            'gallons' => 1,
            'amount' => 30,
            'payment_method' => 'cash',
        ]);
        $afterMidnight->created_at = Carbon::parse('2026-11-14T16:30:00Z')
            ->setTimezone('Asia/Manila');
        $afterMidnight->save();

        $this->assertSame(1, WalkInOrder::whereDate('created_at', Carbon::today())->count());
    }
}

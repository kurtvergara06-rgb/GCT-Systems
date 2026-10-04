<?php

namespace Tests\Unit\Maintenance;

use App\Services\Maintenance\PmsStatusService;
use Carbon\Carbon;
use Tests\TestCase;

class PmsStatusServiceTest extends TestCase
{
    private PmsStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));
        $this->service = app(PmsStatusService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_past_recommended_date_is_overdue_even_when_mileage_is_far_below_threshold(): void
    {
        $this->assertSame(
            'Overdue',
            $this->service->determineStatus(
                1000,
                10000,
                '2026-08-26'
            )
        );
    }

    public function test_mileage_at_or_above_next_pms_is_overdue(): void
    {
        $this->assertSame(
            'Overdue',
            $this->service->determineStatus(
                10000,
                10000,
                '2026-12-01'
            )
        );
    }

    public function test_mileage_within_500_km_is_due_soon(): void
    {
        $this->assertSame(
            'Due Soon',
            $this->service->determineStatus(
                9700,
                10000,
                '2026-12-01'
            )
        );
    }

    public function test_task_outside_warning_range_and_not_past_date_is_upcoming(): void
    {
        $this->assertSame(
            'Upcoming',
            $this->service->determineStatus(
                8000,
                10000,
                '2026-12-01'
            )
        );
    }
}

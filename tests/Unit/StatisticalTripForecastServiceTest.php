<?php

namespace Tests\Unit;

use App\Services\StatisticalTripForecastService;
use PHPUnit\Framework\TestCase;

class StatisticalTripForecastServiceTest extends TestCase
{
    public function test_median_eta_requires_five_matching_route_records(): void
    {
        $service = new StatisticalTripForecastService;
        $departure = '2026-11-05T08:00:00+08:00';
        $target = [['trip_code' => 'T-1', 'route' => 'Route A', 'departure_at' => $departure, 'bus_no' => 'BUS-1']];
        $four = array_map(fn ($v) => ['route' => 'Route A', 'duration_minutes' => $v], [20, 25, 30, 35]);

        $unavailable = $service->forecast($four, $target);
        $this->assertCount(0, $unavailable['predictions']);
        $this->assertFalse($unavailable['eligibility'][0]['eligible']);

        $result = $service->forecast([...$four, ['route' => ' route   a ', 'duration_minutes' => 40]], $target);
        $this->assertSame('laravel-statistical-fallback-v1', $result['model']);
        $this->assertCount(1, $result['predictions']);
        $this->assertSame(30.0, $result['predictions'][0]['predicted_duration_minutes']);
        $this->assertSame('laravel-route-median', $result['predictions'][0]['method']);
        $this->assertSame('BUS-1', $result['predictions'][0]['bus_no']);
        $this->assertSame('2026-11-05T08:30:00+08:00', $result['predictions'][0]['estimated_arrival_at']);
    }

    public function test_different_route_never_supplies_missing_history(): void
    {
        $service = new StatisticalTripForecastService;
        $history = array_fill(0, 10, ['route' => 'Route B', 'duration_minutes' => 40]);
        $result = $service->forecast($history, [
            ['trip_code' => 'T-2', 'route' => 'Route A', 'departure_at' => '2026-11-05T08:00:00+08:00'],
        ]);

        $this->assertSame(0, $result['predicted_target_count']);
        $this->assertSame(0, $result['eligibility'][0]['route_history_count']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripScheduleDepartureIntervalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_same_route_departure_at_fourteen_minutes_is_rejected(): void
    {
        [$route, $date] = $this->prepareSchedule();

        $this->post(route('trip-schedule.store'), [
            'shuttle_route_id' => $route->id,
            'trip_date' => $date,
            'departure_time' => '08:14',
        ])->assertSessionHasErrors('departure_time');

        $this->assertSame(1, TripSchedule::query()->count());
    }

    public function test_same_route_departure_at_fifteen_minutes_is_allowed(): void
    {
        [$route, $date] = $this->prepareSchedule();

        $this->post(route('trip-schedule.store'), [
            'shuttle_route_id' => $route->id,
            'trip_date' => $date,
            'departure_time' => '08:15',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, TripSchedule::query()->count());
        $this->assertDatabaseHas('trip_schedules', [
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:15:00',
        ]);
    }

    private function prepareSchedule(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 07:00:00', 'Asia/Manila'));

        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
        $this->actingAs($user);

        $route = ShuttleRoute::query()->create([
            'route_code' => 'BOUNDARY-01',
            'route_name' => 'Boundary Test Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'distance_km' => 4,
            'estimated_time_minutes' => 15,
            'status' => 'Active',
        ]);

        $date = '2026-10-10';
        TripSchedule::query()->create([
            'trip_code' => 'BOUNDARY-BASE',
            'trip_date' => $date,
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_date' => $date,
            'estimated_arrival_time' => '08:15:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
            'created_by' => $user->id,
        ]);

        return [$route, $date];
    }
}

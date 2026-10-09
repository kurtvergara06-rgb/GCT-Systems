<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Operation\DailyDriverReport;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class TripRecordsIncrementalLoadingTest extends TestCase
{
    use RefreshDatabase;

    private function operationUser(): User
    {
        return User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    public function test_trip_records_are_loaded_in_bounded_server_pages(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-HISTORY',
            'route_name' => 'History Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'distance_km' => 10,
            'estimated_time_minutes' => 30,
            'status' => 'Active',
        ]);

        foreach (range(1, 55) as $index) {
            $date = today()->subDays($index)->toDateString();

            TripSchedule::create([
                'trip_code' => 'T-HISTORY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00:00',
                'estimated_arrival_time' => '08:30:00',
                'estimated_arrival_date' => $date,
                'shift' => 'Morning',
                'assignment_status' => 'Unassigned',
                'status' => 'Completed',
            ]);
        }

        $user = $this->operationUser();

        $this->actingAs($user)
            ->get(route('trip-records'))
            ->assertOk()
            ->assertViewHas('trips', function ($trips): bool {
                return $trips instanceof LengthAwarePaginator
                    && $trips->count() === 50
                    && $trips->total() === 55
                    && $trips->currentPage() === 1;
            })
            ->assertSee('data-lazy-pagination="true"', false)
            ->assertDontSee('>Previous<', false)
            ->assertDontSee('>Next<', false);

        $this->actingAs($user)
            ->get(route('trip-records', ['records_page' => 2]))
            ->assertOk()
            ->assertViewHas('trips', function ($trips): bool {
                return $trips instanceof LengthAwarePaginator
                    && $trips->count() === 5
                    && $trips->total() === 55
                    && $trips->currentPage() === 2;
            });
    }

    public function test_incremental_rows_keep_delegated_details_actions_and_safe_modal_dismissal(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Operation/TripRecordController.php')
        );
        $view = file_get_contents(
            resource_path('views/Operation/Trip_Records/trip-records.blade.php')
        );
        $script = file_get_contents(
            resource_path('js/Operation/Trip_Records/trip-records.js')
        );

        $this->assertStringContainsString('->paginate(self::RECORDS_PER_PAGE', $controller);
        $this->assertStringContainsString('data-scroll-pagination', $view);
        $this->assertStringContainsString('data-lazy-pagination="true"', $view);
        $this->assertStringContainsString("event.target.closest('.view-trip-btn')", $script);
        $this->assertStringContainsString('__gctTripRecordsAbortController', $script);
        $this->assertStringNotContainsString("querySelectorAll('.view-trip-btn')", $script);
        $this->assertStringNotContainsString('e.target === modalOverlay', $script);
    }

    public function test_history_uses_route_snapshot_and_only_recorded_distance(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-SNAPSHOT',
            'route_name' => 'Original Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'distance_km' => 99,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);
        $trip = TripSchedule::create([
            'trip_code' => 'T-SNAPSHOT',
            'trip_date' => '2026-10-01',
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'estimated_arrival_date' => '2026-10-01',
            'actual_departure_time' => '08:00:00',
            'actual_arrival_time' => '08:50:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Completed',
        ]);
        DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-0001',
            'report_date' => '2026-10-01',
            'driver_name' => 'Historical Driver',
            'trip_schedule_id' => $trip->id,
            'trip_ticket' => 'T-SNAPSHOT',
            'from_location' => 'Terminal A',
            'to_location' => 'Terminal B',
            'departure_time' => '08:00:00',
            'arrival_time' => '08:50:00',
            'passengers' => 10,
            'km' => 12.5,
        ]);
        $route->update(['route_name' => 'Renamed Route', 'distance_km' => 120]);

        $this->actingAs($this->operationUser())
            ->get(route('trip-records'))
            ->assertOk()
            ->assertSee('Original Route')
            ->assertViewHas('trips', fn ($trips) => $trips->first()->route_name_snapshot === 'Original Route')
            ->assertViewHas('totalDistanceKm', 12.5);

        $this->assertSame('Original Route', $trip->fresh()->route_name_snapshot);
        $this->assertSame(99.0, (float) $trip->fresh()->planned_distance_km);
    }

    public function test_overnight_on_time_rate_excludes_incomplete_actual_timestamps(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-NIGHT',
            'route_name' => 'Night Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'distance_km' => 10,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);
        TripSchedule::create([
            'trip_code' => 'T-NIGHT-1', 'trip_date' => '2026-10-01',
            'shuttle_route_id' => $route->id, 'departure_time' => '23:30:00',
            'estimated_arrival_time' => '00:30:00', 'estimated_arrival_date' => '2026-10-02',
            'actual_departure_time' => '23:32:00', 'actual_arrival_time' => '00:25:00',
            'shift' => 'Night', 'assignment_status' => 'Unassigned', 'status' => 'Completed',
        ]);
        TripSchedule::create([
            'trip_code' => 'T-NIGHT-2', 'trip_date' => '2026-10-01',
            'shuttle_route_id' => $route->id, 'departure_time' => '22:00:00',
            'estimated_arrival_time' => '23:00:00', 'estimated_arrival_date' => '2026-10-01',
            'actual_departure_time' => null, 'actual_arrival_time' => null,
            'shift' => 'Night', 'assignment_status' => 'Unassigned', 'status' => 'Completed',
        ]);

        $this->actingAs($this->operationUser())
            ->get(route('trip-records'))
            ->assertOk()
            ->assertViewHas('onTimeRate', 100.0);
    }
}

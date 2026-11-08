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

    public function test_legacy_history_links_use_one_unified_table_and_toolbar(): void
    {
        $user = $this->operationUser();

        foreach (['assignments', 'operations'] as $view) {
            $response = $this->actingAs($user)
                ->get(route('trip-records', ['view' => $view]))
                ->assertOk()
                ->assertSee('class="trip-history-controls"', false)
                ->assertSee('data-table-toolbar="true"', false)
                ->assertSee('data-ui-component="table-footer"', false)
                ->assertSeeText('Search')
                ->assertSeeText('Filter')
                ->assertSeeText('Sort');

            $response->assertSee('Unified Trip History')
                ->assertSee('Assignment Outcome')
                ->assertSee('Operational Status')
                ->assertDontSee('class="trip-history-tabs"', false);
        }
    }

    public function test_unified_history_includes_elapsed_unassigned_trips_and_terminal_operations(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-UNIFIED',
            'route_name' => 'Unified Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'status' => 'Active',
        ]);
        $date = now(config('app.business_timezone', 'Asia/Manila'))->subDays(2)->toDateString();
        foreach (['Scheduled' => 'T-UNIFIED-ELAPSED', 'Completed' => 'T-UNIFIED-COMPLETED'] as $status => $code) {
            TripSchedule::create([
                'trip_code' => $code,
                'trip_date' => $date,
                'shuttle_route_id' => $route->id,
                'departure_time' => '08:00:00',
                'estimated_arrival_time' => '09:00:00',
                'estimated_arrival_date' => $date,
                'status' => $status,
                'assignment_status' => 'Unassigned',
            ]);
        }

        foreach ([[], ['view' => 'assignments'], ['view' => 'operations']] as $parameters) {
            $this->actingAs($this->operationUser())
                ->get(route('trip-records', $parameters))
                ->assertOk()
                ->assertSee('T-UNIFIED-ELAPSED')
                ->assertSee('T-UNIFIED-COMPLETED')
                ->assertSee('Assigned Resources')
                ->assertSee('Bus Slots')
                ->assertSee('Actual Times')
                ->assertDontSee('class="trip-history-tabs"', false)
                ->assertViewHas('trips', fn ($trips) => $trips->total() === 2);
        }
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
            ->get(route('trip-records', ['view' => 'operations']))
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
            ->get(route('trip-records', ['view' => 'operations', 'records_page' => 2]))
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
        $this->assertStringContainsString('<x-ui.table-footer :items="$trips" data-lazy-pagination="true"', $view);
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
            ->get(route('trip-records', ['view' => 'operations']))
            ->assertOk()
            ->assertSee('Original Route')
            ->assertViewHas('trips', fn ($trips) => $trips->first()->route_name_snapshot === 'Original Route')
            ->assertViewHas('totalDistanceKm', 12.5);

        $this->assertSame('Original Route', $trip->fresh()->route_name_snapshot);
        $this->assertSame(99.0, (float) $trip->fresh()->planned_distance_km);
    }

    public function test_unified_history_actual_times_are_sourced_from_linked_ddr_not_schedule_fields(): void
    {
        $route = ShuttleRoute::create([
            'route_code' => 'R-DDR-ACTUAL',
            'route_name' => 'Actual Time Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'status' => 'Active',
        ]);
        $trip = TripSchedule::create([
            'trip_code' => 'T-DDR-ACTUAL',
            'trip_date' => '2026-10-01',
            'shuttle_route_id' => $route->id,
            'departure_time' => '08:00:00',
            'estimated_arrival_time' => '09:00:00',
            'estimated_arrival_date' => '2026-10-01',
            'actual_departure_time' => '08:15:00',
            'actual_arrival_time' => '09:30:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Completed',
        ]);
        $this->actingAs($this->operationUser())
            ->get(route('trip-records', ['search' => 'T-DDR-ACTUAL']))
            ->assertOk()
            ->assertSee('No DDR recorded')
            ->assertDontSee('08:15:00 → 09:30:00');

        $bus = \App\Models\Maintenance\Bus::create([
            'bus_no' => 'DDR-TEST-BUS',
            'plate_no' => 'DDR-1234',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);
        $report = DailyDriverReport::create([
            'ddr_no' => 'DDR-2026-1234',
            'report_date' => '2026-10-01',
            'trip_schedule_id' => $trip->id,
            'trip_ticket' => 'T-DDR-ACTUAL',
            'driver_id' => 'DDR-DRIVER-1',
            'driver_name' => 'Verified Driver',
            'bus_id' => $bus->id,
            'from_location' => 'Terminal A',
            'to_location' => 'Terminal B',
            'departure_time' => '08:05:00',
            'arrival_time' => '09:10:00',
            'passengers' => 10,
            'encoded_by' => $this->operationUser()->id,
        ]);
        $report->additionalTrips()->create([
            'sequence' => 2,
            'trip_schedule_id' => $trip->id,
            'trip_ticket' => 'T-DDR-ACTUAL-ADDITIONAL',
            'departure_time' => '10:00:00',
            'arrival_time' => '11:00:00',
        ]);

        $this->actingAs($this->operationUser())
            ->get(route('trip-records', ['search' => 'T-DDR-ACTUAL']))
            ->assertOk()
            ->assertSee('2 DDR trip records')
            ->assertSee('DDR-2026-1234')
            ->assertSee('8:05 AM')
            ->assertSee('9:10 AM')
            ->assertSee('10:00 AM')
            ->assertSee('11:00 AM');
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
            ->get(route('trip-records', ['view' => 'operations']))
            ->assertOk()
            ->assertViewHas('onTimeRate', 100.0);
    }

    public function test_unified_history_shows_single_resource_in_table_and_sticky_view_action(): void
    {
        $view = file_get_contents(resource_path('views/Operation/Trip_Records/trip-records.blade.php'));
        $layout = file_get_contents(resource_path('views/components/operation/trip-history-layout.blade.php'));
        $styles = file_get_contents(resource_path('css/Operation/Trip_Records/trip-records.css'));

        $this->assertStringContainsString("['driver_name']", $view);
        $this->assertStringContainsString('data-trip=', $view);
        $this->assertStringNotContainsString('more — View for details', $view);
        $this->assertStringContainsString('gct-responsive-records gct-sticky-actions', $layout);
        $this->assertStringContainsString('.unified-trip-history-table > tbody > tr > td:last-child:not([colspan])', $styles);
        $this->assertStringContainsString('right: 0;', $styles);
        $this->assertStringContainsString('col.history-col-action { width: 110px; }', $styles);
        $this->assertStringContainsString('table-layout: fixed;', $styles);
        $this->assertStringContainsString('width: 84px;', $styles);
        $this->assertStringContainsString('class="history-col-action"', $view);
        $this->assertStringContainsString('class="trip-history-view-action view-trip-btn"', $view);
        $this->assertStringNotContainsString('class="trip-action view view-trip-btn"', $view);
        $this->assertStringContainsString('button.trip-history-view-action', $styles);
        $this->assertStringContainsString('width: 94px;', $styles);
    }

}

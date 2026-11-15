<?php

namespace Tests\Feature;

use App\Models\Admin\User;
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
}

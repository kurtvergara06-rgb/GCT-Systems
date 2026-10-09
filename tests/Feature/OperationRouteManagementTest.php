<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationRouteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_staff_without_edit_permission_can_view_but_cannot_mutate_route_master(): void
    {
        $staff = $this->operationUser('staff');

        $rolePermission = RolePermission::query()
            ->where(
                'role_key',
                $staff->permissionRoleKey()
            )
            ->firstOrFail();

        $permissions = $rolePermission->permissions;

        data_set(
            $permissions,
            'operation.edit',
            false
        );

        $rolePermission->update([
            'permissions' => $permissions,
        ]);

        $route = $this->route([
            'route_code' => 'R-01',
            'route_name' => 'Tanauan - Malvar',
        ]);

        $page = $this
            ->actingAs($staff)
            ->get(route('operation.routes'));

        $page
            ->assertOk()
            ->assertSee($route->route_name)
            ->assertDontSee('id="openRouteModal"', false)
            ->assertDontSee('edit-route-btn', false)
            ->assertDontSee('open-delete-route-modal', false);

        $this
            ->actingAs($staff)
            ->post(
                route('operation.routes.store'),
                $this->validRoutePayload()
            )
            ->assertForbidden();
    }

    public function test_legacy_route_mapping_columns_are_removed_after_data_migration(): void
    {
        $this->assertFalse(
            Schema::hasColumn(
                'shuttle_routes',
                'origin_location_source'
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'shuttle_routes',
                'destination_location_source'
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'shuttle_routes',
                'distance_manually_adjusted'
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'shuttle_routes',
                'time_manually_adjusted'
            )
        );
    }

    public function test_reverse_location_resolves_a_manual_pin_to_an_editable_place_label(): void
    {
        $head = $this->operationUser('head');

        Http::fake([
            'photon.komoot.io/reverse*' =>
                Http::response([
                    'features' => [[
                        'geometry' => [
                            'coordinates' => [
                                121.1490,
                                14.0830,
                            ],
                        ],
                        'properties' => [
                            'osm_id' => 12345,
                            'name' => 'Tanauan City Hall',
                            'city' => 'Tanauan',
                            'state' => 'Batangas',
                            'country' => 'Philippines',
                        ],
                    ]],
                ], 200),
        ]);

        $this
            ->actingAs($head)
            ->getJson(
                route(
                    'operation.routes.reverse-location',
                    [
                        'latitude' => 14.0830,
                        'longitude' => 121.1490,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'place.name',
                'Tanauan City Hall'
            )
            ->assertJsonPath(
                'place.source',
                'Manual Pin'
            );
    }

    public function test_route_frontend_preserves_confirmed_coordinates_when_only_the_label_is_edited(): void
    {
        $javascript = file_get_contents(
            resource_path(
                'js/Operation/Routes/routes-stops.js'
            )
        );

        $this->assertStringContainsString(
            'input.dataset.selectedName =',
            $javascript
        );

        $this->assertStringContainsString(
            'editedLabel;',
            $javascript
        );

        $this->assertStringContainsString(
            'function updateAutoRouteName',
            $javascript
        );

        $this->assertStringContainsString(
            'async function reversePinnedLocation',
            $javascript
        );
    }

    public function test_routes_use_toast_and_inline_feedback_without_legacy_validation_modal(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/Operation/Routes/routes-stops.blade.php'
            )
        );

        $javascript = file_get_contents(
            resource_path(
                'js/Operation/Routes/routes-stops.js'
            )
        );

        $recovery = file_get_contents(
            resource_path(
                'js/Operation/Routes/route-validation-recovery.js'
            )
        );

        $this->assertStringNotContainsString(
            'routeValidationModal',
            $view
        );

        $this->assertStringNotContainsString(
            'routeValidationModal',
            $javascript
        );

        $this->assertStringNotContainsString(
            'routeValidationModal',
            $recovery
        );

        $this->assertStringContainsString(
            'Validation errors are shown by the shared system toast and inline field feedback.',
            $view
        );
    }

    public function test_route_edit_frontend_preserves_route_identity_for_validation_recovery(): void
    {
        $javascript = file_get_contents(
            resource_path(
                'js/Operation/Routes/routes-stops.js'
            )
        );

        $recovery = file_get_contents(
            resource_path(
                'js/Operation/Routes/route-validation-recovery.js'
            )
        );

        $this->assertStringContainsString(
            'id:',
            $javascript
        );

        $this->assertStringContainsString(
            'button.dataset.id',
            $javascript
        );

        $this->assertStringContainsString(
            'setValue(routeEditingId, route.id);',
            $javascript
        );

        $this->assertStringContainsString(
            'oldInput.editing_route_id',
            $recovery
        );

        $this->assertStringContainsString(
            '.edit-route-btn[data-id=',
            $recovery
        );
    }

    public function test_route_store_uses_server_verified_distance_eta_and_stop_dwell_time(): void
    {
        $head = $this->operationUser('head');

        $this->fakeOsrm(
            distanceMeters: 5000,
            durationSeconds: 300
        );

        $payload = $this->validRoutePayload([
            'route_name' => 'Tanauan - Malvar via Darasa',
            'distance_km' => 1,
            'calculated_distance_km' => 1,
            'calculated_time_minutes' => 1,
            'route_geometry' => '{"tampered":true}',
            'stops' => [
                'Darasa',
                'Malvar Crossing',
            ],
            'stop_addresses' => [
                'Darasa, Tanauan',
                'Malvar Crossing',
            ],
            'stop_latitudes' => [
                14.0700,
                14.0550,
            ],
            'stop_longitudes' => [
                121.1500,
                121.1450,
            ],
            'stop_sources' => [
                'OpenStreetMap',
                'OpenStreetMap',
            ],
        ]);

        $this
            ->actingAs($head)
            ->post(
                route('operation.routes.store'),
                $payload
            )
            ->assertRedirect('/operation/routes')
            ->assertSessionHasNoErrors();

        $route = ShuttleRoute::query()
            ->where(
                'route_name',
                'Tanauan - Malvar via Darasa'
            )
            ->firstOrFail();

        $this->assertSame(
            '5.00',
            (string) $route->distance_km
        );

        $this->assertSame(
            '5.00',
            (string) $route->calculated_distance_km
        );

        // 5 km / 25 kph = 12 min, plus 1 minute dwell for each of 2 stops.
        $this->assertSame(
            14,
            $route->estimated_time_minutes
        );

        $this->assertSame(
            14,
            $route->calculated_time_minutes
        );

        $this->assertFalse(
            (bool) $route->distance_is_manual
        );

        $this->assertFalse(
            (bool) $route->time_is_manual
        );

        $this->assertSame(
            'OSRM Operational ETA',
            $route->distance_source
        );

        $this->assertCount(
            2,
            $route->stops
        );
    }

    public function test_origin_and_destination_cannot_resolve_to_same_physical_point(): void
    {
        $head = $this->operationUser('head');

        $payload = $this->validRoutePayload([
            'route_name' => 'Invalid Same Point',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'origin_latitude' => 14.0830000,
            'origin_longitude' => 121.1490000,
            'destination_latitude' => 14.0831000,
            'destination_longitude' => 121.1490000,
        ]);

        $this
            ->actingAs($head)
            ->post(
                route('operation.routes.store'),
                $payload
            )
            ->assertSessionHasErrors([
                'destination',
            ]);

        $this->assertDatabaseMissing(
            'shuttle_routes',
            [
                'route_name' =>
                    'Invalid Same Point',
            ]
        );
    }

    public function test_entered_stop_requires_confirmed_coordinates_on_backend(): void
    {
        $head = $this->operationUser('head');

        $payload = $this->validRoutePayload([
            'route_name' => 'Missing Stop Coordinates',
            'stops' => [
                'Darasa',
            ],
            'stop_addresses' => [
                'Darasa, Tanauan',
            ],
            'stop_latitudes' => [
                null,
            ],
            'stop_longitudes' => [
                null,
            ],
        ]);

        $this
            ->actingAs($head)
            ->post(
                route('operation.routes.store'),
                $payload
            )
            ->assertSessionHasErrors([
                'stops.0',
            ]);
    }

    public function test_used_route_ignores_structural_edits_and_allows_status_only(): void
    {
        $head = $this->operationUser('head');

        $route = $this->route([
            'route_code' => 'R-10',
            'route_name' => 'Protected Historical Route',
        ]);

        TripSchedule::create([
            'trip_code' => 'T-HISTORY-001',
            'trip_date' => now()
                ->subDay()
                ->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '06:00:00',
            'estimated_arrival_time' => '06:15:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Completed',
            'created_by' => $head->id,
        ]);

        $this
            ->actingAs($head)
            ->get(route('operation.routes'))
            ->assertOk()
            ->assertSee(
                'data-has-schedules="1"',
                false
            )
            ->assertDontSee(
                'deleteRouteForm-' . $route->id,
                false
            );

        $this
            ->actingAs($head)
            ->put(
                route(
                    'operation.routes.update',
                    $route
                ),
                [
                    'status' => 'Inactive',
                    'route_name' =>
                        'Tampered Historical Name',
                    'origin' =>
                        'Tampered Origin',
                ]
            )
            ->assertRedirect(
                '/operation/routes'
            )
            ->assertSessionHasNoErrors();

        $route->refresh();

        $this->assertSame(
            'Protected Historical Route',
            $route->route_name
        );

        $this->assertSame(
            'Tanauan',
            $route->origin
        );

        $this->assertSame(
            'Inactive',
            $route->status
        );
    }

    public function test_route_cannot_be_deactivated_while_current_or_future_trips_exist(): void
    {
        $head = $this->operationUser('head');

        $route = $this->route([
            'route_code' => 'R-20',
            'route_name' => 'Future Scheduled Route',
        ]);

        TripSchedule::create([
            'trip_code' => 'T-FUTURE-001',
            'trip_date' => now()
                ->addDay()
                ->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '07:00:00',
            'estimated_arrival_time' => '07:15:00',
            'shift' => 'Morning',
            'assignment_status' => 'Unassigned',
            'status' => 'Scheduled',
            'created_by' => $head->id,
        ]);

        $this
            ->actingAs($head)
            ->put(
                route(
                    'operation.routes.update',
                    $route
                ),
                [
                    'status' => 'Inactive',
                ]
            )
            ->assertSessionHasErrors([
                'status',
            ]);

        $this->assertSame(
            'Active',
            $route->fresh()->status
        );
    }

    public function test_manual_assignment_rejects_trip_on_inactive_route(): void
    {
        $head = $this->operationUser('head');

        $route = $this->route([
            'route_code' => 'R-25',
            'route_name' => 'Inactive Assignment Route',
            'status' => 'Inactive',
        ]);

        $driver = Driver::firstOrCreate(
            [
                'driver_id' =>
                    'D-ROUTE-INACTIVE-001',
            ],
            [
                'driver_name' =>
                    'Inactive Route Driver',
                'shift' =>
                    'Morning',
                'employment_status' =>
                    'Active',
            ]
        );

        $attendance = DriverAttendance::create([
            'driver_name' =>
                $driver->driver_name,
            'shift' =>
                'Morning',
            'attendance_date' =>
                now()->toDateString(),
            'status' =>
                'Present',
        ]);

        $bus = Bus::create([
            'bus_no' =>
                'BUS-ROUTE-INACTIVE-001',
            'plate_no' =>
                'RTE-2501',
            'bus_model' =>
                'Route Test Bus',
            'capacity' =>
                40,
            'status' =>
                'Active',
        ]);

        $trip = TripSchedule::create([
            'trip_code' =>
                'T-INACTIVE-001',
            'trip_date' =>
                now()->toDateString(),
            'shuttle_route_id' =>
                $route->id,
            'departure_time' =>
                '06:00:00',
            'estimated_arrival_time' =>
                '06:15:00',
            'shift' =>
                'Morning',
            'assignment_status' =>
                'Unassigned',
            'status' =>
                'Scheduled',
            'created_by' =>
                $head->id,
        ]);

        $this
            ->actingAs($head)
            ->post(
                route(
                    'driver-bus-assignment.store'
                ),
                [
                    'trip_schedule_id' =>
                        $trip->id,
                    'driver_attendance_id' =>
                        $attendance->id,
                    'bus_id' =>
                        $bus->id,
                ]
            )
            ->assertSessionHasErrors([
                'trip_schedule_id',
            ]);

        $this->assertDatabaseMissing(
            'trip_assignments',
            [
                'trip_schedule_id' =>
                    $trip->id,
            ]
        );
    }

    public function test_same_endpoints_can_have_distinct_named_route_variants(): void
    {
        $head = $this->operationUser('head');

        $this->route([
            'route_code' => 'R-30',
            'route_name' =>
                'Tanauan - Malvar Express',
        ]);

        $this->fakeOsrm(
            distanceMeters: 4400,
            durationSeconds: 360
        );

        $this
            ->actingAs($head)
            ->post(
                route('operation.routes.store'),
                $this->validRoutePayload([
                    'route_name' =>
                        'Tanauan - Malvar via Darasa',
                ])
            )
            ->assertRedirect('/operation/routes')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(
            'shuttle_routes',
            [
                'route_name' =>
                    'Tanauan - Malvar via Darasa',
            ]
        );
    }

    public function test_route_name_is_normalized_before_duplicate_validation(): void
    {
        $head = $this->operationUser('head');

        $this->route([
            'route_code' => 'R-40',
            'route_name' =>
                'Tanauan - Malvar',
        ]);

        $this
            ->actingAs($head)
            ->post(
                route('operation.routes.store'),
                $this->validRoutePayload([
                    'route_name' =>
                        '  Tanauan   -   Malvar  ',
                ])
            )
            ->assertSessionHasErrors([
                'route_name',
            ]);
    }

    public function test_route_calculation_uses_stale_verified_cache_when_provider_temporarily_fails(): void
    {
        $head = $this->operationUser('head');

        Cache::flush();

        $points = [
            [
                'latitude' => 14.08123,
                'longitude' => 121.14877,
            ],
            [
                'latitude' => 14.04421,
                'longitude' => 121.15687,
            ],
        ];

        $sequence = Http::sequence()
            ->push([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 5000,
                    'duration' => 300,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [121.14877, 14.08123],
                            [121.15687, 14.04421],
                        ],
                    ],
                ]],
            ], 200)
            ->push([], 500)
            ->push([], 500);

        Http::fake([
            'router.project-osrm.org/*' =>
                $sequence,
        ]);

        $this
            ->actingAs($head)
            ->post(
                route(
                    'operation.routes.calculate'
                ),
                [
                    'points' => $points,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'source',
                'OSRM Operational ETA'
            );

        $coordinates = collect($points)
            ->map(
                fn (array $point) =>
                    sprintf(
                        '%.7F,%.7F',
                        $point['longitude'],
                        $point['latitude']
                    )
            )
            ->implode(';');

        $cacheHash = sha1(
            $coordinates
            . '|'
            . number_format(
                max(
                    5.0,
                    (float) config(
                        'services.osrm.operational_speed_kph',
                        25
                    )
                ),
                2,
                '.',
                ''
            )
            . '|'
            . max(
                0,
                (int) config(
                    'services.osrm.stop_dwell_minutes',
                    1
                )
            )
        );

        Cache::forget(
            'route-osrm:v4:'
            . $cacheHash
        );

        $this
            ->actingAs($head)
            ->post(
                route(
                    'operation.routes.calculate'
                ),
                [
                    'points' => $points,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'source',
                'OSRM Cached Operational ETA'
            );
    }

    private function operationUser(
        string $role
    ): User {
        return User::factory()->create([
            'department' => 'Operation',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function route(
        array $overrides = []
    ): ShuttleRoute {
        return ShuttleRoute::create(
            array_merge([
                'route_code' =>
                    'R-TEST-'
                    . str_pad(
                        (string) (
                            ShuttleRoute::query()->count()
                            + 1
                        ),
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                'route_name' =>
                    'Tanauan - Malvar',
                'origin' =>
                    'Tanauan',
                'origin_address' =>
                    'Tanauan City, Batangas',
                'origin_latitude' =>
                    14.0830,
                'origin_longitude' =>
                    121.1490,
                'origin_source' =>
                    'OpenStreetMap',
                'destination' =>
                    'Malvar',
                'destination_address' =>
                    'Malvar, Batangas',
                'destination_latitude' =>
                    14.0450,
                'destination_longitude' =>
                    121.1550,
                'destination_source' =>
                    'OpenStreetMap',
                'distance_km' =>
                    5.00,
                'estimated_time_minutes' =>
                    12,
                'calculated_distance_km' =>
                    5.00,
                'calculated_time_minutes' =>
                    12,
                'distance_source' =>
                    'OSRM Operational ETA',
                'distance_is_manual' =>
                    false,
                'time_is_manual' =>
                    false,
                'route_geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [
                        [121.1490, 14.0830],
                        [121.1550, 14.0450],
                    ],
                ],
                'route_calculated_at' =>
                    now(),
                'status' =>
                    'Active',
            ], $overrides)
        );
    }

    private function validRoutePayload(
        array $overrides = []
    ): array {
        return array_merge([
            'route_name' =>
                'Tanauan - Malvar New',
            'origin' =>
                'Tanauan',
            'origin_address' =>
                'Tanauan City, Batangas',
            'origin_latitude' =>
                14.0830,
            'origin_longitude' =>
                121.1490,
            'origin_source' =>
                'OpenStreetMap',
            'destination' =>
                'Malvar',
            'destination_address' =>
                'Malvar, Batangas',
            'destination_latitude' =>
                14.0450,
            'destination_longitude' =>
                121.1550,
            'destination_source' =>
                'OpenStreetMap',
            'status' =>
                'Active',
        ], $overrides);
    }

    private function fakeOsrm(
        int $distanceMeters,
        int $durationSeconds
    ): void {
        Http::fake([
            'router.project-osrm.org/*' =>
                Http::response([
                    'code' => 'Ok',
                    'routes' => [[
                        'distance' =>
                            $distanceMeters,
                        'duration' =>
                            $durationSeconds,
                        'geometry' => [
                            'type' =>
                                'LineString',
                            'coordinates' => [
                                [121.1490, 14.0830],
                                [121.1550, 14.0450],
                            ],
                        ],
                    ]],
                ], 200),
        ]);
    }
}

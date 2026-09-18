<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Maintenance\Bus;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Incident;
use App\Models\Operation\IncidentResponse;
use App\Models\Operation\ShuttleRoute;
use App\Models\Operation\TripAssignment;
use App\Models\Operation\TripSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Driver $driver;

    protected Bus $bus;

    protected TripSchedule $tripSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
        ]);

        $this->driver = Driver::firstOrCreate(
            ['driver_id' => 'D-TEST-001'],
            [
                'driver_name' => 'Test Driver',
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]
        );

        $this->bus = Bus::create([
            'bus_no' => 'BUS-TEST-001',
            'plate_no' => 'ABC-1234',
            'bus_model' => 'Test Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $route = ShuttleRoute::create([
            'route_code' => 'R-T001',
            'route_name' => 'Test Route',
            'origin' => 'Terminal A',
            'destination' => 'Terminal B',
            'distance_km' => 25,
            'estimated_time_minutes' => 60,
            'status' => 'Active',
        ]);

        $attendance = DriverAttendance::create([
            'driver_name' => 'Test Driver',
            'shift' => 'Morning',
            'attendance_date' => now()->toDateString(),
            'status' => 'Present',
        ]);

        $this->tripSchedule = TripSchedule::create([
            'trip_code' => 'T-T001',
            'trip_date' => now()->toDateString(),
            'shuttle_route_id' => $route->id,
            'departure_time' => '05:00:00',
            'estimated_arrival_time' => '06:00:00',
            'shift' => 'Morning',
            'assignment_status' => 'Assigned',
            'status' => 'Dispatched',
            'created_by' => $this->user->id,
        ]);

        TripAssignment::create([
            'trip_schedule_id' => $this->tripSchedule->id,
            'driver_attendance_id' => $attendance->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'bus_id' => $this->bus->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_driver_can_submit_an_incident(): void
    {
        $payload = [
            'trip_schedule_id' => $this->tripSchedule->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'incident_type' => 'Bus Breakdown',
            'location' => 'Along Highway, Batangas City',
            'description' => 'Engine overheating. Bus cannot continue.',
        ];

        $this->actingAs($this->user)
            ->post(route('incidents.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incidents', [
            'incident_no' => 'INC-00001',
            'incident_type' => 'Bus Breakdown',
            'location' => 'Along Highway, Batangas City',
            'status' => 'Reported',
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->driver_id,
        ]);

        $this->assertDatabaseHas('incident_responses', [
            'status' => 'Reported',
            'notes' => 'Incident reported by driver.',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->user)
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [])
            ->assertSessionHasErrors(['incident_type', 'location']);
    }

    public function test_incident_type_must_be_valid(): void
    {
        $this->actingAs($this->user)
            ->from(route('incidents.create'))
            ->post(route('incidents.store'), [
                'incident_type' => 'InvalidType',
                'location' => 'Some Location',
            ])
            ->assertSessionHasErrors(['incident_type']);
    }

    public function test_unauthorized_users_cannot_manipulate_incidents(): void
    {
        $guest = new \stdClass;
        $guest->id = null;
        $guest->department = null;
        $guest->role = null;

        $this->get(route('incidents'))
            ->assertRedirect(route('login'));

        $this->post(route('incidents.store'), [
            'incident_type' => 'Traffic',
            'location' => 'Somewhere',
        ])->assertRedirect(route('login'));
    }

    public function test_operations_can_view_incidents(): void
    {
        $this->actingAs($this->user)
            ->get(route('incidents'))
            ->assertOk()
            ->assertSee('Incident Management');
    }

    public function test_operations_can_update_incident_status(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->put(route('incidents.update', ['incident' => $incident->incident_no]), [
                'status' => 'Monitoring',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'Monitoring',
        ]);

        $this->assertDatabaseHas('incident_responses', [
            'incident_id' => $incident->id,
            'status' => 'Monitoring',
        ]);
    }

    public function test_breakdown_incident_can_dispatch_eligible_replacement_bus(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $replacementBus = Bus::create([
            'bus_no' => 'BUS-REPL-001',
            'plate_no' => 'XYZ-5678',
            'bus_model' => 'Spare Bus',
            'capacity' => 40,
            'status' => 'Active',
        ]);

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $replacementBus->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incident_replacements', [
            'incident_id' => $incident->id,
            'original_bus_id' => $this->bus->id,
            'replacement_bus_id' => $replacementBus->id,
        ]);

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'Replacement Bus Dispatched',
        ]);
    }

    public function test_unavailable_buses_cannot_be_dispatched(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $inactiveBus = Bus::create([
            'bus_no' => 'BUS-INACTIVE',
            'plate_no' => 'ZZZ-0000',
            'status' => 'Inactive',
        ]);

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $inactiveBus->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('incident_replacements', 0);
    }

    public function test_replacement_bus_cannot_be_same_as_original(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $this->bus->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('incident_replacements', 0);
    }

    public function test_incident_resolution_records_correct_timestamps(): void
    {
        $incident = $this->createIncident('Traffic');

        $this->actingAs($this->user)
            ->put(route('incidents.update', ['incident' => $incident->incident_no]), [
                'status' => 'Resolved',
                'resolution_notes' => 'Traffic cleared. Trip resumed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'Resolved',
            'resolution_notes' => 'Traffic cleared. Trip resumed.',
        ]);

        $dbIncident = Incident::find($incident->id);
        $this->assertNotNull($dbIncident->resolved_at);
        $this->assertEquals($this->user->id, $dbIncident->resolved_by);
    }

    public function test_trip_incident_relationship_works_correctly(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $incident->load('tripSchedule', 'bus', 'responses');

        $this->assertNotNull($incident->tripSchedule);
        $this->assertEquals($this->tripSchedule->id, $incident->tripSchedule->id);
        $this->assertEquals('T-T001', $incident->tripSchedule->trip_code);

        $this->assertNotNull($incident->bus);
        $this->assertEquals($this->bus->id, $incident->bus->id);

        $this->assertTrue($incident->responses->count() >= 1);
    }

    public function test_non_breakdown_incident_cannot_dispatch_replacement(): void
    {
        $incident = $this->createIncident('Traffic');

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->post(route('incidents.dispatch', ['incident' => $incident->incident_no]), [
                'replacement_bus_id' => $this->bus->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('incident_replacements', 0);
    }

    public function test_incident_number_is_unique_and_sequential(): void
    {
        $incident1 = $this->createIncident('Traffic');
        $incident2 = $this->createIncident('Bus Breakdown');

        $this->assertEquals('INC-00001', $incident1->incident_no);
        $this->assertEquals('INC-00002', $incident2->incident_no);
    }

    public function test_create_page_renders_with_trip_context(): void
    {
        $this->actingAs($this->user)
            ->get(route('incidents.create'))
            ->assertOk()
            ->assertSee('Report Incident')
            ->assertSee('Your Active Assignment');
    }

    public function test_show_page_renders_incident_details(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $this->actingAs($this->user)
            ->get(route('incidents.show', ['incident' => $incident->incident_no]))
            ->assertOk()
            ->assertSee($incident->incident_no)
            ->assertSee('Bus Breakdown')
            ->assertSee('Operations Actions');
    }

    public function test_search_filters_incidents(): void
    {
        $incident = $this->createIncident('Bus Breakdown');

        $this->actingAs($this->user)
            ->get(route('incidents', ['search' => $incident->incident_no]))
            ->assertOk()
            ->assertSee($incident->incident_no);
    }

    public function test_type_filter_filters_incidents(): void
    {
        $incident1 = $this->createIncident('Bus Breakdown');
        $incident2 = $this->createIncident('Traffic');

        $this->actingAs($this->user)
            ->get(route('incidents', ['type' => 'Bus Breakdown']))
            ->assertOk()
            ->assertSee($incident1->incident_no)
            ->assertDontSee($incident2->incident_no);
    }

    public function test_status_filter_filters_incidents(): void
    {
        $incident1 = $this->createIncident('Traffic');

        $this->actingAs($this->user)
            ->get(route('incidents', ['status' => 'Monitoring']))
            ->assertOk()
            ->assertDontSee($incident1->incident_no);
    }

    public function test_add_response_records_response(): void
    {
        $incident = $this->createIncident('Traffic');

        $this->actingAs($this->user)
            ->from(route('incidents.show', ['incident' => $incident->incident_no]))
            ->post(route('incidents.response', ['incident' => $incident->incident_no]), [
                'notes' => 'Investigating traffic situation.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('incident_responses', [
            'incident_id' => $incident->id,
            'notes' => 'Investigating traffic situation.',
        ]);
    }

    public function test_index_page_renders_kpi_summary(): void
    {
        $this->createIncident('Bus Breakdown');

        $this->actingAs($this->user)
            ->get(route('incidents'))
            ->assertOk()
            ->assertSee('Total Incidents')
            ->assertSee('Active Incidents')
            ->assertSee('Active Breakdowns')
            ->assertSee('Resolved Today');
    }

    private function createIncident(string $type): Incident
    {
        $nextNumber = Incident::count() + 1;

        $incident = Incident::create([
            'incident_no' => 'INC-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT),
            'trip_schedule_id' => $this->tripSchedule->id,
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driver->driver_id,
            'driver_name' => $this->driver->driver_name,
            'incident_type' => $type,
            'location' => 'Test Location',
            'description' => 'Test incident',
            'incident_reported_at' => now(),
            'status' => 'Reported',
            'reported_by' => $this->user->id,
        ]);

        IncidentResponse::create([
            'incident_id' => $incident->id,
            'status' => 'Reported',
            'notes' => 'Incident reported by driver.',
            'responded_by' => $this->user->id,
            'created_at' => now(),
        ]);

        return $incident;
    }
}

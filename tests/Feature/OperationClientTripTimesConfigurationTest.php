<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Operation\ClientTripTimeConfiguration;
use App\Models\Operation\ClientTripTimeSlot;
use App\Models\Operation\OperationClient;
use App\Support\ClientTripTimes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationClientTripTimesConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function operationUser(string $role = 'head'): User
    {
        return User::factory()->create([
            'department' => 'Operation',
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function setOperationEditPermission(User $user, bool $enabled): void
    {
        $permission = RolePermission::query()
            ->where('role_key', $user->permissionRoleKey())
            ->firstOrFail();

        $permissions = $permission->permissions;
        data_set($permissions, 'operation.view', true);
        data_set($permissions, 'operation.edit', $enabled);
        $permission->update(['permissions' => $permissions]);
    }

    public function test_authorized_user_can_view_client_trip_times_page(): void
    {
        $user = $this->operationUser();
        $client = OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('Client Trip Times')
            ->assertDontSee("\u{FEFF}", false)
            ->assertSee('class="ctt-client-card"', false)
            ->assertSee('data-configured-count', false)
            ->assertSee('data-category-chip="incoming_am"', false)
            ->assertSee('data-stat="configured"', false)
            ->assertSee('data-configured-value', false)
            ->assertSee('data-stat="complete-clients"', false)
            ->assertSee('data-stat="needs-setup"', false)
            ->assertSee('id="cttClientFilter"', false)
            ->assertSee('name="sort"', false)
            ->assertSee('id="cttClientToolbar"', false)
            ->assertSee('data-client-filter="true"', false)
            ->assertSee('data-table-tool="search"', false)
            ->assertSee('data-table-tool="filter"', false)
            ->assertSee('data-table-tool="sort"', false)
            ->assertSee('Allocated Buses')
            ->assertSee('Manage in Bus Master List')
            ->assertSee('Client Configurations')
            ->assertSee('id="cttToggleAllClients"', false)
            ->assertSee('id="cttSearchEmpty" hidden', false);

        $this->actingAs($user)
            ->get(route('client-trip-times.panel', [$client->id, ClientTripTimes::CATEGORY_INCOMING_AM]))
            ->assertOk()
            ->assertSee('data-client-id="'.$client->id.'"', false)
            ->assertSee('data-category="'.ClientTripTimes::CATEGORY_INCOMING_AM.'"', false)
            ->assertSee('Not Configured');
    }

    public function test_can_create_configuration_with_slots(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'is_active' => true,
                'slots' => ['09:30'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('client_trip_time_configurations', [
            'operation_client_id' => $client->id,
            'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
            'is_active' => true,
        ]);

        $config = ClientTripTimeConfiguration::query()->first();
        $this->assertNotNull($config);
        $this->assertDatabaseHas('client_trip_time_slots', [
            'client_trip_time_configuration_id' => $config->id,
            'slot_time' => '09:30:00',
            'is_enabled' => true,
        ]);
    }

    public function test_slot_creation_enforces_window_inclusively_and_rejects_duplicates(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'Range Validation Client']);
        $configuration = ClientTripTimeConfiguration::create([
            'operation_client_id' => $client->id,
            'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
            'start_time' => '06:00:00',
            'end_time' => '10:00:00',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.slots.store', $configuration), ['slot_time' => '00:45'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slot_time');

        $this->actingAs($user)
            ->postJson(route('client-trip-times.slots.store', $configuration), ['slot_time' => '10:01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slot_time');

        $this->actingAs($user)
            ->postJson(route('client-trip-times.slots.store', $configuration), ['slot_time' => '06:00'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('client-trip-times.slots.store', $configuration), ['slot_time' => '10:00'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('client-trip-times.slots.store', $configuration), ['slot_time' => '06:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slot_time');

        $this->assertDatabaseCount('client_trip_time_slots', 2);
    }

    public function test_start_time_cannot_be_after_end_time(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
                'start_time' => '11:00',
                'end_time' => '09:00',
                'slots' => ['10:00'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
    }

    public function test_am_configuration_rejects_pm_time_range(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
                'start_time' => '13:00',
                'end_time' => '14:00',
                'slots' => ['13:30'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_pm_configuration_rejects_am_time_range(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_PM,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'slots' => ['10:00'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_disabled_category_rejects_scheduled_departures(): void
    {
        $client = OperationClient::create(['name' => 'Restricted Client']);
        ClientTripTimeConfiguration::create([
            'operation_client_id' => $client->id,
            'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'is_active' => false,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        ClientTripTimes::assertTripDepartureAllowed('Restricted Client', 'Incoming', '09:00');
    }

    public function test_inactive_client_cannot_create_or_edit_trip_time_configuration(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'Inactive Client', 'status' => 'Inactive']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
                'start_time' => '08:00',
                'end_time' => '10:00',
                'slots' => ['09:00'],
            ])
            ->assertUnprocessable();

        $configuration = ClientTripTimeConfiguration::create([
            'operation_client_id' => $client->id,
            'category' => ClientTripTimes::CATEGORY_INCOMING_AM,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson(route('client-trip-times.update', $configuration), [
                'start_time' => '08:00',
                'end_time' => '11:00',
            ])
            ->assertUnprocessable();
    }

    public function test_pm_category_rejects_morning_start_even_if_end_is_pm(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'Boundary Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_INCOMING_PM,
                'start_time' => '06:00',
                'end_time' => '23:59',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_am_category_rejects_afternoon_end_even_if_start_is_am(): void
    {
        $user = $this->operationUser();
        $this->setOperationEditPermission($user, true);
        $client = OperationClient::create(['name' => 'Boundary Client']);

        $this->actingAs($user)
            ->postJson(route('client-trip-times.store'), [
                'operation_client_id' => $client->id,
                'category' => ClientTripTimes::CATEGORY_OUTGOING_AM,
                'start_time' => '06:00',
                'end_time' => '13:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
    }
}

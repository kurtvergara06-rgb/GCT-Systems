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
        OperationClient::create(['name' => 'GCT Test Client']);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('Client Trip Times');
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
}


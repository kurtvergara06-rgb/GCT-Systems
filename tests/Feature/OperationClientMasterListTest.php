<?php

namespace Tests\Feature;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Models\Operation\OperationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationClientMasterListTest extends TestCase
{
    use RefreshDatabase;

    private function operationUser(bool $canEdit): User
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $permission = RolePermission::query()->where('role_key', $user->permissionRoleKey())->firstOrFail();
        $permissions = $permission->permissions;
        data_set($permissions, 'operation.view', true);
        data_set($permissions, 'operation.edit', $canEdit);
        $permission->update(['permissions' => $permissions]);

        return $user;
    }

    public function test_client_creation_modal_is_available_on_trip_times_page(): void
    {
        $user = $this->operationUser(true);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('id="cttCreateClientModal"', false)
            ->assertSee('id="cttAddClientButton"', false)
            ->assertDontSee('Client Master List');
    }

    public function test_authorized_operations_user_can_create_client_from_trip_times(): void
    {
        $user = $this->operationUser(true);

        $this->actingAs($user)
            ->post(route('operation.clients.store'), ['name' => '  Example Client  '])
            ->assertRedirect(route('client-trip-times'));

        $this->assertDatabaseHas('operation_clients', ['name' => 'Example Client']);
    }

    public function test_duplicate_client_name_is_rejected_case_insensitively(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'EPSON']);

        $this->actingAs($user)
            ->from(route('client-trip-times'))
            ->post(route('operation.clients.store'), ['name' => 'epson'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('operation_clients', 1);
    }

    public function test_view_only_operations_user_cannot_create_clients(): void
    {
        $user = $this->operationUser(false);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertDontSee('id="cttAddClientButton"', false);

        $this->actingAs($user)
            ->post(route('operation.clients.store'), ['name' => 'Unauthorized'])
            ->assertForbidden();

        $this->assertDatabaseMissing('operation_clients', ['name' => 'Unauthorized']);
    }
    public function test_client_status_can_be_changed_without_deleting_existing_records(): void
    {
        $user = $this->operationUser(true);
        $client = OperationClient::create(['name' => 'Status Test Client']);

        $this->actingAs($user)
            ->patch(route('operation.clients.status', $client), ['status' => 'Inactive'])
            ->assertRedirect(route('client-trip-times'));

        $this->assertDatabaseHas('operation_clients', ['id' => $client->id, 'status' => 'Inactive']);

        $this->actingAs($user)
            ->patch(route('operation.clients.status', $client), ['status' => 'Active'])
            ->assertRedirect(route('client-trip-times'));

        $this->assertDatabaseHas('operation_clients', ['id' => $client->id, 'status' => 'Active']);
    }

    public function test_view_only_staff_cannot_change_client_status(): void
    {
        $user = $this->operationUser(false);
        $client = OperationClient::create(['name' => 'Protected Client']);

        $this->actingAs($user)
            ->patch(route('operation.clients.status', $client), ['status' => 'Inactive'])
            ->assertForbidden();

        $this->assertDatabaseHas('operation_clients', ['id' => $client->id, 'status' => 'Active']);
    }

    public function test_client_trip_times_page_displays_database_backed_status(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'Paused Client', 'status' => 'Inactive']);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('Paused Client')
            ->assertSee('Inactive')
            ->assertSee('data-confirm-form', false)
            ->assertSee('Reactivate Client?');
    }

}

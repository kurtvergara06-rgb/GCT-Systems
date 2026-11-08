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

    public function test_client_trip_times_uses_master_list_clients_without_duplicate_creation_ui(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'Newly Registered Client', 'status' => 'Active']);

        $this->actingAs($user)
            ->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('Client Configurations')
            ->assertSee('Newly Registered Client')
            ->assertDontSee('id="cttCreateClientModal"', false)
            ->assertDontSee('id="cttAddClientButton"', false)
            ->assertDontSee('id="cttCreateClientModal"', false);
    }

    public function test_newly_registered_master_list_client_appears_in_trip_times_without_manual_sync(): void
    {
        $user = $this->operationUser(true);
        $this->actingAs($user)
            ->post(route('operation.clients.store'), [
                'name' => 'Shuttle Partner A',
                'contact_person' => 'Test Coordinator',
                'contact_number' => '09123456789',
                'return_to' => 'client-master-list',
            ])
            ->assertRedirect(route('operation.clients.index'));

        $this->actingAs($user)->get(route('client-trip-times'))
            ->assertOk()
            ->assertSee('Shuttle Partner A')
            ->assertSee('Configure client');
    }

    public function test_client_master_list_shows_view_and_edit_but_preserves_existing_actions(): void
    {
        $client = OperationClient::create([
            'name' => 'Client Profile Company',
            'status' => 'Active',
            'contact_person' => 'Original Contact',
        ]);

        $this->actingAs($this->operationUser(true))
            ->get(route('operation.clients.index'))
            ->assertOk()
            ->assertSee('data-client-mode="view"', false)
            ->assertSee('data-client-mode="edit"', false)
            ->assertSee('data-client-update-url="'.route('operation.clients.update', $client).'"', false)
            ->assertSee('Trip Times')
            ->assertSee('Deactivate')
            ->assertSee('clientMasterDetailModal');

        $this->actingAs($this->operationUser(false))
            ->get(route('operation.clients.index'))
            ->assertOk()
            ->assertSee('data-client-mode="view"', false)
            ->assertDontSee('data-client-mode="edit"', false);
    }

    public function test_client_actions_use_icon_only_buttons_with_accessible_labels(): void
    {
        $client = OperationClient::create(['name' => 'Icon Button Client', 'status' => 'Active']);

        $this->actingAs($this->operationUser(true))
            ->get(route('operation.clients.index'))
            ->assertOk()
            ->assertSee('aria-label="View Icon Button Client"', false)
            ->assertSee('aria-label="Edit Icon Button Client"', false)
            ->assertSee('aria-label="View trip times for Icon Button Client"', false)
            ->assertSee('aria-label="Deactivate Icon Button Client"', false)
            ->assertSee('fa-eye', false)
            ->assertSee('fa-pen-to-square', false)
            ->assertSee('fa-clock', false)
            ->assertSee('fa-power-off', false)
            ->assertDontSee('<span>Trip Times</span>', false)
            ->assertDontSee('<span>Edit</span>', false);

        $styles = file_get_contents(resource_path('css/Operation/Clients/client-master-list.css'));
        $this->assertStringContainsString('min-width: 184px;', $styles);
        $this->assertStringContainsString('width: 34px;', $styles);
    }

    public function test_client_details_can_be_corrected_without_changing_client_id_or_allocations(): void
    {
        $client = OperationClient::create(['name' => 'Original Client', 'status' => 'Active']);
        $payload = [
            'name' => 'Corrected Client Company',
            'company_address' => 'Updated Address',
            'contact_person' => 'Updated Officer',
            'contact_position' => 'HR Manager',
            'contact_number' => '09123456789',
            'contact_email' => 'contact@example.com',
        ];

        $this->actingAs($this->operationUser(true))
            ->put(route('operation.clients.update', $client), $payload + ['id' => 9000, 'status' => 'Inactive'])
            ->assertRedirect(route('operation.clients.index'))
            ->assertSessionHasNoErrors();

        $client->refresh();
        $this->assertSame('Corrected Client Company', $client->name);
        $this->assertSame('Updated Address', $client->company_address);
        $this->assertSame('Updated Officer', $client->contact_person);
        $this->assertSame('HR Manager', $client->contact_position);
        $this->assertSame('09123456789', $client->contact_number);
        $this->assertSame('contact@example.com', $client->contact_email);
        $this->assertSame('Active', $client->status);
    }

    public function test_client_update_rejects_duplicate_name_and_denies_view_only_user(): void
    {
        $client = OperationClient::create(['name' => 'Original Client', 'status' => 'Active']);
        OperationClient::create(['name' => 'Existing Company', 'status' => 'Active']);
        $payload = [
            'name' => 'existing company',
            'contact_person' => 'Contact',
        ];

        $this->actingAs($this->operationUser(true))
            ->put(route('operation.clients.update', $client), $payload)
            ->assertSessionHasErrors('name');

        $this->actingAs($this->operationUser(false))
            ->put(route('operation.clients.update', $client), [
                'name' => 'Unauthorized Rename',
            ])
            ->assertForbidden();

        $this->assertSame('Original Client', $client->fresh()->name);
    }

    public function test_client_master_list_shows_database_records_and_status_counts(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'EPSON', 'status' => 'Active']);
        OperationClient::create(['name' => 'Archived Client', 'status' => 'Inactive']);

        $this->actingAs($user)->get(route('operation.clients.index'))
            ->assertOk()
            ->assertSee('Client Master List')
            ->assertSee('Active Clients')
            ->assertSee('Inactive Clients')
            ->assertSee('id="clientMasterToolbar"', false)
            ->assertSee('EPSON')
            ->assertDontSee('Archived Client</strong>', false)
            ->assertDontSee('client-master-filter-btn')
            ->assertDontSee('client-master-clear')
            ->assertSee('data-client-create-open', false);

        $this->actingAs($user)->get(route('operation.clients.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Archived Client</strong>', false)
            ->assertDontSee('EPSON</strong>', false);
    }

    public function test_client_master_list_search_and_status_filter(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'EPSON', 'status' => 'Active']);
        OperationClient::create(['name' => 'Inactive Client', 'status' => 'Inactive']);

        $this->actingAs($user)
            ->get(route('operation.clients.index', ['search' => 'EPSON', 'tab' => 'active']))
            ->assertOk()
            ->assertSee('EPSON')
            ->assertDontSee('Inactive Client</strong>', false);
    }

    public function test_client_master_list_tab_preserves_search_and_sort_controls(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'Dormant Company', 'status' => 'Inactive']);

        $this->actingAs($user)->get(route('operation.clients.index', [
            'tab' => 'history',
            'search' => 'Dormant',
            'sort' => 'created_at',
            'sort_direction' => 'desc',
        ]))
            ->assertOk()
            ->assertSee('Dormant Company</strong>', false)
            ->assertSee('name="sort_direction"', false)
            ->assertSee('name="search"', false)
            ->assertSee('name="tab" value="history"', false);
    }

    public function test_client_master_list_filter_by_configuration(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create(['name' => 'Needs Setup Client', 'status' => 'Active']);

        $this->actingAs($user)->get(route('operation.clients.index', [
            'setup' => 'needs-setup',
            'tab' => 'active',
        ]))
            ->assertOk()
            ->assertSee('Needs Setup Client</strong>', false);
    }

    public function test_view_only_user_can_view_client_master_list_but_not_create(): void
    {
        $user = $this->operationUser(false);

        $this->actingAs($user)->get(route('operation.clients.index'))
            ->assertOk()
            ->assertDontSee('data-client-create-open', false);

        $this->actingAs($user)
            ->post(route('operation.clients.store'), ['name' => 'Unauthorized Client', 'return_to' => 'client-master-list'])
            ->assertForbidden();
    }

    public function test_client_created_from_master_list_returns_to_master_list(): void
    {
        $user = $this->operationUser(true);
        $this->actingAs($user)
            ->post(route('operation.clients.store'), [
                'name' => 'New Shuttle Client',
                'company_address' => 'Malvar, Batangas',
                'contact_person' => 'Test Coordinator',
                'contact_position' => 'HR Officer',
                'contact_number' => '09123456789',
                'contact_email' => 'coordinator@example.test',
                'service_start_date' => '2026-11-01',
                'return_to' => 'client-master-list',
            ])
            ->assertRedirect(route('operation.clients.index'));

        $this->assertDatabaseHas('operation_clients', [
            'name' => 'New Shuttle Client',
            'contact_person' => 'Test Coordinator',
            'contact_number' => '09123456789',
            'contact_email' => 'coordinator@example.test',
        ]);
    }

    public function test_master_list_requires_primary_contact_details(): void
    {
        $user = $this->operationUser(true);
        $this->actingAs($user)
            ->post(route('operation.clients.store'), [
                'name' => 'Missing Contact Company',
                'return_to' => 'client-master-list',
            ])
            ->assertSessionHasErrors(['contact_person', 'contact_number']);

        $this->assertDatabaseMissing('operation_clients', ['name' => 'Missing Contact Company']);
    }

    public function test_client_master_list_shows_existing_contact_details(): void
    {
        $user = $this->operationUser(true);
        OperationClient::create([
            'name' => 'Contact Enabled',
            'contact_person' => 'Operations Coordinator',
            'contact_number' => '09123456789',
        ]);

        $this->actingAs($user)->get(route('operation.clients.index'))
            ->assertOk()
            ->assertSee('Operations Coordinator')
            ->assertSee('09123456789');
    }

    public function test_trip_times_client_creation_remains_backwards_compatible(): void
    {
        $user = $this->operationUser(true);
        $this->actingAs($user)
            ->post(route('operation.clients.store'), ['name' => 'Legacy Style Client'])
            ->assertRedirect(route('client-trip-times'));

        $this->assertDatabaseHas('operation_clients', ['name' => 'Legacy Style Client']);
    }

    public function test_client_status_changed_from_master_list_preserves_record(): void
    {
        $user = $this->operationUser(true);
        $client = OperationClient::create(['name' => 'Paused Service']);

        $this->actingAs($user)
            ->patch(route('operation.clients.status', $client), ['status' => 'Inactive', 'return_to' => 'client-master-list'])
            ->assertRedirect(route('operation.clients.index'));

        $this->assertDatabaseHas('operation_clients', ['id' => $client->id, 'status' => 'Inactive']);
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

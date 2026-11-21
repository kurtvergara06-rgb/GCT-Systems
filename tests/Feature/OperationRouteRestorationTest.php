<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OperationRouteRestorationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $department, string $role = 'head'): User
    {
        return User::factory()->create([
            'department' => $department,
            'role' => $role,
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    public function test_restored_driver_and_mechanic_master_list_urls_exist(): void
    {
        foreach ([
            'operation.personnel.drivers',
            'operation.personnel.drivers.store',
            'operation.personnel.drivers.update',
            'operation.personnel.drivers.deactivate',
            'operation.personnel.mechanics',
            'maintenance.personnel.mechanics',
            'maintenance.personnel.mechanics.store',
            'maintenance.personnel.mechanics.update',
            'maintenance.personnel.mechanics.deactivate',
            'maintenance.personnel.mechanics.activate',
            'maintenance.personnel.mechanics.specializations.store',
            'maintenance.personnel.mechanics.specializations.destroy',
            'operation.attendance.batch.roster',
            'operation.attendance.batch.store',
            'maintenance.attendance.batch.roster',
            'maintenance.attendance.batch.store',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), "Missing route: {$name}");
        }
    }

    public function test_operation_can_view_but_not_mutate_maintenance_owned_mechanics(): void
    {
        $op = $this->actor('Operation');
        $this->actingAs($op)->get(route('operation.personnel.mechanics'))
            ->assertOk()->assertDontSee('Add Mechanic');

        $this->actingAs($op)->post(route('operation.personnel.mechanics.store'), [])
            ->assertForbidden();
        $this->actingAs($op)->get(route('mechanic-attendance'))->assertForbidden();
        $this->actingAs($op)->post(route('mechanic-attendance.store'), [])->assertForbidden();
        $this->actingAs($op)->getJson('/maintenance/attendance/batch/mechanic?date=2026-11-21')->assertForbidden();
    }

    public function test_batch_roster_urls_match_the_existing_browser_requests(): void
    {
        $date = now(config('app.business_timezone', 'Asia/Manila'))->toDateString();
        $op = $this->actor('Operation');
        $maintenance = $this->actor('Maintenance');

        $this->actingAs($op)->getJson('/operation/attendance/batch/driver?date='.$date.'&shift=all')
            ->assertOk()->assertJsonStructure(['rows']);
        $this->actingAs($maintenance)->getJson('/maintenance/attendance/batch/mechanic?date='.$date.'&shift=Morning')
            ->assertOk()->assertJsonStructure(['rows']);
    }

    public function test_batch_attendance_checks_department_before_any_input_validation(): void
    {
        $op = $this->actor('Operation');
        $maintenance = $this->actor('Maintenance');

        $this->actingAs($op)->postJson(route('operation.attendance.batch.store', ['type' => 'mechanic']), [])
            ->assertForbidden();
        $this->actingAs($maintenance)->postJson(route('operation.attendance.batch.store', ['type' => 'driver']), [])
            ->assertForbidden();
        $this->actingAs($maintenance)->postJson(route('maintenance.attendance.batch.store'), [])
            ->assertUnprocessable();
    }

    public function test_retired_bus_csv_import_is_not_exposed(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('bus-master-list.import'));
        $this->actingAs($this->actor('Operation'))
            ->post('/bus-master-list/import')
            ->assertStatus(405);
    }
}

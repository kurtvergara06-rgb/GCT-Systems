<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Services\DepartmentAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DepartmentAnalyticsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $department = 'Maintenance', string $role = 'head'): User
    {
        return User::factory()->create(['department' => $department, 'role' => $role, 'status' => 'Active',
            'must_change_password' => false, 'onboarding_completed' => true]);
    }

    private function permissions(User $user, bool $view, bool $analyze = false, bool $recommendations = false): void
    {
        $role = RolePermission::where('role_key', $user->permissionRoleKey())->firstOrFail();
        $permissions = $role->permissions;
        $permissions['analytics'] = ['view' => $view, 'diagnostic' => $analyze, 'predictive' => $analyze, 'recommendations' => $recommendations];
        $role->update(['permissions' => $permissions]);
    }

    private function page(string $stage = 'descriptive', array $query = []): string
    {
        if ($stage === 'descriptive') {
            return route('analytics.descriptive', $query);
        }
        return route('analytics.stage', ['stage' => $stage] + $query);
    }

    public function test_each_department_head_and_staff_inherit_only_their_authorized_stages(): void
    {
        Http::fake();
        foreach (['Operation', 'Maintenance', 'Warehouse', 'Purchase'] as $department) {
            foreach (['head', 'staff'] as $type) {
                $user = $this->account($department, $type);
                $this->permissions($user, true);
                $response = $this->actingAs($user)->get($this->page());
                $response->assertOk()->assertViewHas('department', strtolower($department))
                    ->assertSee('aria-current="page"', false);
                foreach (['diagnostic', 'predictive', 'prescriptive'] as $stage) {
                    $response->assertDontSee('href="'.$this->page($stage).'"', false);
                    $this->get($this->page($stage))->assertForbidden();
                }
                $this->get(route('analytics.descriptive'))->assertOk()->assertViewIs('Admin.Analytics.descriptive.layout');
                $this->get(route('analytics.stage', ['stage' => 'predictive']))->assertForbidden();
            }
        }
    }

    public function test_grants_and_revocation_apply_to_same_role_accounts_without_changing_roles(): void
    {
        Http::fake();
        $first = $this->account(); $second = $this->account();
        $this->permissions($first, true, true, true);
        foreach ([$first, $second] as $user) {
            foreach (['descriptive', 'diagnostic', 'predictive', 'prescriptive'] as $stage) {
                $this->actingAs($user)->get($this->page($stage))->assertOk();
            }
        }
        $this->permissions($first, false, true, true); // Corrupt/legacy combination must fail closed at read time.
        foreach ([$first, $second] as $user) {
            $this->assertFalse($user->hasSystemPermission('analytics', 'diagnostic'));
            $this->assertFalse($user->hasSystemPermission('analytics', 'predictive'));
            $this->assertFalse($user->hasSystemPermission('analytics', 'recommendations'));
            foreach (['descriptive', 'diagnostic', 'predictive', 'prescriptive'] as $stage) {
                $this->actingAs($user)->get($this->page($stage))->assertForbidden();
            }
            $this->actingAs($user)->get(route('maintenance-dashboard'))->assertOk()
                ->assertDontSee('href="'.$this->page().'"', false);
        }
    }

    public function test_admin_editor_rejects_missing_view_dependency_and_invalid_boolean_values(): void
    {
        $admin = $this->account('Admin');
        $role = RolePermission::where('role_key', 'maintenance_head')->firstOrFail();
        $before = $role->permissions;
        foreach (['diagnostic', 'predictive', 'recommendations'] as $capability) {
            $this->actingAs($admin)->putJson(route('admin.roles-permissions.update', $role), [
                'permissions' => ['analytics' => ['view' => false, $capability => true]],
            ])->assertUnprocessable()->assertJsonValidationErrors('permissions.analytics.'.$capability);
        }
        $this->putJson(route('admin.roles-permissions.update', $role), [
            'permissions' => ['analytics' => ['view' => 'invalid']],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.analytics.view');
        $this->assertSame($before, $role->fresh()->permissions);
    }

    public function test_valid_permission_updates_preserve_department_isolation_and_admin_protection(): void
    {
        $admin = $this->account('Admin'); $staff = $this->account('Maintenance', 'staff');
        $role = RolePermission::where('role_key', 'maintenance_staff')->firstOrFail();
        $payload = ['permissions' => ['maintenance' => ['view' => true], 'analytics' => ['view' => true, 'diagnostic' => true, 'predictive' => true],
            'warehouse' => ['view' => true], 'administration' => ['full_control' => true]]];
        $this->actingAs($staff)->putJson(route('admin.roles-permissions.update', $role), $payload)->assertForbidden();
        $this->actingAs($admin)->put(route('admin.roles-permissions.update', $role), $payload)->assertRedirect();
        $this->assertTrue($staff->hasSystemPermission('analytics', 'diagnostic'));
        $this->assertTrue($staff->hasSystemPermission('analytics', 'predictive'));
        $this->assertFalse($staff->hasSystemPermission('warehouse', 'view'));
        $this->assertFalse($staff->hasSystemPermission('administration', 'full_control'));
        $this->putJson(route('admin.roles-permissions.update', 'admin_head'), $payload)->assertForbidden();
        $this->get(route('analytics.descriptive'))->assertOk();
    }

    public function test_maintenance_aggregates_exclude_unowned_other_department_jobs_and_url_scope_spoofing(): void
    {
        $maintenance = $this->account(); $other = $this->account('Operation');
        $this->permissions($maintenance, true);
        foreach ([$maintenance, $other] as $index => $owner) {
            DB::table('job_orders')->insert(['job_order_no' => 'ANALYTICS-'.$index, 'bus_no' => 'QA-BUS',
                'problem_issue' => 'Confidential source detail '.$index, 'maintenance_type' => 'Corrective',
                'status' => 'On Going', 'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $response = $this->actingAs($maintenance)->get($this->page('descriptive', ['department' => 'operation']));
        $response->assertOk()->assertViewHas('department', 'maintenance')->assertViewHas('maintenanceAnalytics', fn ($m) => $m['createdCount'] === 1)->assertDontSee('Confidential source detail')->assertDontSee('purchase_orders');
    }

    public function test_purchasing_excludes_unhanded_requests_and_warehouse_excludes_demo_inventory(): void
    {
        $now = now();
        foreach (['Submitted', 'For Purchase'] as $index => $status) {
            DB::table('purchase_requests')->insert(['pr_no' => 'PR-ANALYTICS-'.$index,'job_order_no' => 'QA-JOB',
                'bus_no' => 'QA-BUS','item' => 'Private item','quantity' => 1,'status' => $status,'created_at' => $now,'updated_at' => $now]);
        }
        foreach (['app', 'demo'] as $index => $source) {
            DB::table('inventory_items')->insert(['item_code' => 'QA-'.$index,'item_name' => 'Private stock',
                'category' => 'QA','source' => $source,'created_at' => $now,'updated_at' => $now]);
        }
        $service = app(DepartmentAnalyticsService::class);
        $dates = [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()];
        $purchase = $service->summary('purchase', ...$dates);
        $warehouse = $service->summary('warehouse', ...$dates);
        $this->assertSame(1, collect($purchase['metrics'])->firstWhere('label', 'Purchase requests')['total']);
        $this->assertSame(1, collect($warehouse['metrics'])->firstWhere('label', 'Inventory items')['total']);
    }

    public function test_reporting_dates_are_validated_and_exclude_outside_period_records(): void
    {
        $user = $this->account(); $this->permissions($user, true);
        foreach ([now(), now()->subYear()] as $date) {
            DB::table('fuel_reports')->insert(['report_date' => $date->toDateString(), 'bus_no' => 'QA-BUS',
                'created_at' => $date,'updated_at' => $date]);
        }
        $this->actingAs($user)->get($this->page('descriptive', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertViewHas('maintenanceAnalytics', fn ($m) => $m['fuelRecordCount'] === 1);
        $this->getJson($this->page('descriptive', ['from' => 'invalid']))->assertUnprocessable();
        $this->getJson($this->page('descriptive', ['from' => '2026-11-20','to' => '2026-11-01']))->assertUnprocessable();
        $this->getJson($this->page('descriptive', ['from' => '2020-01-01']))->assertUnprocessable();
    }

    public function test_reachable_or_demo_models_are_not_reported_as_department_ready(): void
    {
        $user = $this->account(); $this->permissions($user, true, true);
        Http::fake(['*/fuel/status' => Http::response(['success' => true,'model_ready' => true,
            'data_source' => 'demo','is_production_model' => true,'sample_count' => 500])]);
        $this->actingAs($user)->get($this->page('predictive'))->assertOk()
            ->assertViewIs('Admin.Analytics.predictive.layout')
            ->assertSee('Fuel Consumption Forecast')->assertSee('Not connected for this output')->assertDontSee('Trip Delay & ETA');
        Http::assertNothingSent(); // The scoped historical view never calls Admin ML endpoints.

    }

    public function test_disconnected_and_invalid_models_have_accurate_unavailable_states(): void
    {
        Http::fake(['*/fuel/status' => Http::response(['unexpected' => true])]);
        $rows = app(DepartmentAnalyticsService::class)->modelReadiness('maintenance');
        $this->assertTrue($rows[0]['reachable']); $this->assertFalse($rows[0]['model_ready']);
        Http::fake(['*/fuel/status' => Http::failedConnection()]);
        $rows = app(DepartmentAnalyticsService::class)->modelReadiness('maintenance');
        $this->assertFalse($rows[0]['reachable']);
        $this->assertSame([], app(DepartmentAnalyticsService::class)->modelReadiness('purchase'));
    }

}

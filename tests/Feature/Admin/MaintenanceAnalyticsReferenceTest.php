<?php

namespace Tests\Feature\Admin;

use App\Models\Admin\User;
use App\Models\Admin\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaintenanceAnalyticsReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        return User::factory()->create(['department'=>'Maintenance','role'=>'head']);
    }

    private function grants(bool $view = true, bool $analyze = true, bool $recommendations = true): void
    {
        $role = RolePermission::where('role_key','maintenance_head')->firstOrFail();
        $permissions = $role->permissions;
        $permissions['analytics'] = ['view' => $view, 'diagnostic' => $analyze, 'predictive' => $analyze, 'recommendations' => $recommendations];
        $role->update(['permissions'=>$permissions]);
    }

    private function page(string $stage, array $query = []): string
    {
        return $stage === 'descriptive' ? route('analytics.descriptive',$query) : route('analytics.stage',['stage'=>$stage]+$query);
    }

    private function job(User $owner, string $reference, array $attributes = []): int
    {
        return DB::table('job_orders')->insertGetId($attributes + [
            'job_order_no'=>$reference,'bus_no'=>'QA-A','problem_issue'=>'Verified test issue','maintenance_type'=>'Corrective',
            'status'=>'Completed','created_by'=>$owner->id,'created_at'=>now()->subDays(3),'updated_at'=>now(),
            'actual_started_at'=>now()->subDays(2)->startOfDay()->addHours(8),
            'actual_completed_at'=>now()->subDays(2)->startOfDay()->addHours(11),
        ]);
    }

    public function test_all_four_original_routes_render_maintenance_references_with_permission_enforcement(): void
    {
        Http::fake();
        $user = $this->account(); $this->grants(false,false,false);
        foreach (['descriptive','diagnostic','predictive','prescriptive'] as $stage) {
            $this->actingAs($user)->get($this->page($stage))->assertForbidden();
        }
        $this->grants(true,false,false);
        $this->get($this->page('descriptive'))->assertOk()->assertSee('Total Job Orders')->assertDontSee('"fuelForecast"',false);
        foreach (['diagnostic','predictive','prescriptive'] as $stage) $this->get($this->page($stage))->assertForbidden();
        $this->grants();
        foreach (['descriptive','diagnostic','predictive','prescriptive'] as $stage) {
            $this->get($this->page($stage))->assertOk()->assertViewIs('Admin.Analytics.'.$stage.'.layout')
                ->assertSee('maintenanceAnalyticsData',false)->assertSee('All Buses')->assertDontSee('Illustrative reporting figures');
        }
        $dates = ['from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()];
        $this->get(route('analytics.department.stage',['stage'=>'diagnostic','bus'=>'all']+$dates+['issue'=>'parts']))
            ->assertRedirect(route('analytics.stage',['stage'=>'diagnostic','bus'=>'all']+$dates+['issue'=>'parts']));
        Http::assertNothingSent();
    }

    public function test_real_metrics_exclude_foreign_jobs_invalid_durations_and_negative_fuel(): void
    {
        $this->travelTo(now()->setDate(2026,11,21)->startOfDay()->addHours(12));
        $user = $this->account(); $this->grants();
        foreach ([2,3,4] as $index=>$hours) {
            $start = now()->subDays(2)->startOfDay()->addHours(8);
            $this->job($user,'VALID-'.$index,['actual_started_at'=>$start,'actual_completed_at'=>$start->copy()->addHours($hours)]);
        }
        $this->job($user,'LEGACY',['actual_started_at'=>null,'actual_completed_at'=>null]);
        $other = User::factory()->create(['department'=>'Operation','role'=>'head']);
        $this->job($other,'SECRET',['bus_no'=>'SECRET-BUS']);
        foreach ([['QA-A',25],['QA-B',10],['QA-B',10],['QA-B',10],['QA-A',-90]] as [$number,$liters]) {
            DB::table('fuel_reports')->insert(['report_date'=>'2026-11-20','bus_no'=>$number,'distance_km'=>100,'fuel_liters'=>$liters,'created_at'=>now(),'updated_at'=>now()]);
        }
        $response=$this->actingAs($user)->get($this->page('descriptive',['from'=>'2026-11-01','to'=>'2026-11-21','domain'=>'inventory','preview'=>1]));
        $response->assertOk()->assertViewHas('maintenanceAnalytics',fn ($m)=>$m['createdCount']===4 && $m['durationSampleCount']===3
            && $m['kpis'][2]['value']==='3.0 hrs' && $m['kpis'][3]['value']==='55.0 L')
            ->assertDontSee('SECRET-BUS')->assertDontSee('Administrative Summary');
        $this->get($this->page('descriptive',['bus'=>'SECRET-BUS']))->assertUnprocessable();
        $this->get($this->page('descriptive',['from'=>'2020-01-01','to'=>'2026-11-21']))->assertUnprocessable();
        $this->get($this->page('descriptive',['from'=>'invalid']))->assertRedirect();
    }

    public function test_verified_delays_respect_parts_timing_rule_and_recommendations_remain_read_only(): void
    {
        $user=$this->account();$this->grants();
        $this->job($user,'OVERDUE',['status'=>'On Going','actual_started_at'=>now()->subHours(5),'actual_completed_at'=>null,
            'estimated_duration_value'=>2,'estimated_duration_unit'=>'Hours','part_needed'=>null]);
        $this->job($user,'WAITING',['status'=>'On Hold','part_needed'=>'Brake Pad','part_status'=>'Not Requested',
            'actual_started_at'=>null,'actual_completed_at'=>null,'estimated_duration_value'=>1,'estimated_duration_unit'=>'Hours']);
        $this->job($user,'LEGACY-CLOSED',['status'=>'On Going','completion_date'=>now(),'actual_started_at'=>now()->subHours(8),
            'actual_completed_at'=>null,'estimated_duration_value'=>1,'estimated_duration_unit'=>'Hours']);
        $before=DB::table('job_orders')->orderBy('id')->get()->toJson();
        $notifications=DB::table('topbar_notifications')->count();
        $this->actingAs($user)->get($this->page('diagnostic'))->assertOk()->assertSee('Repair Delay')->assertSee('Parts-Related Delay')
            ->assertViewHas('maintenanceAnalytics',fn($m)=>collect($m['factors'])->firstWhere('label','Extended Repair')['count']===1);
        $this->get($this->page('prescriptive'))->assertOk()->assertSee('Recommended Actions')->assertSee('do not automatically approve')
            ->assertSee('View Details');
        $this->assertSame($before,DB::table('job_orders')->orderBy('id')->get()->toJson());
        $this->assertSame($notifications,DB::table('topbar_notifications')->count());
    }

    public function test_predictions_are_unavailable_without_real_history_and_label_verified_baselines(): void
    {
        $user=$this->account();$this->grants();
        $this->actingAs($user)->get($this->page('predictive'))->assertOk()->assertSee('Insufficient historical data')
            ->assertViewHas('maintenanceAnalytics',fn($m)=>$m['repairEstimate']===null && $m['fuelEstimate']===null);
        foreach (range(1,3) as $index) {
            $this->job($user,'HISTORY-'.$index);
            DB::table('fuel_reports')->insert(['report_date'=>now()->startOfMonth()->subMonths($index)->addDays(3)->toDateString(),
                'bus_no'=>'QA-A','fuel_liters'=>100*$index,'distance_km'=>1000,'created_at'=>now(),'updated_at'=>now()]);
        }
        $this->get($this->page('predictive'))->assertOk()->assertSee('Rule-Based Estimate')->assertSee('Not connected for this output')
            ->assertViewHas('maintenanceAnalytics',fn($m)=>$m['repairEstimate']===3.0 && $m['fuelEstimate']===200.0);
    }
}

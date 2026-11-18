<?php

namespace Tests\Feature\Maintenance;

use App\Models\Admin\User;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MechanicAttendanceVoidTest extends TestCase
{
    use RefreshDatabase;

    private function adminApprover(): User
    {
        return User::factory()->create([
            'department' => 'Admin',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);
    }

    private function attendance(): MechanicAttendance
    {
        $mechanic = Mechanic::create([
            'mechanic_id' => 'M-VOID-01',
            'mechanic_name' => 'Audit Mechanic',
            'shift' => 'Morning',
            'specialization' => 'Engine',
            'employment_status' => 'Active',
        ]);

        return MechanicAttendance::create([
            'mechanic_id' => $mechanic->mechanic_id,
            'mechanic_name' => $mechanic->mechanic_name,
            'shift' => 'Morning',
            'attendance_date' => '2026-11-17',
            'status' => 'Present',
            'time_in' => '08:00',
        ]);
    }

    public function test_void_requires_a_reason_and_preserves_original_when_validation_fails(): void
    {
        $attendance = $this->attendance();

        $this->actingAs($this->adminApprover())
            ->delete(route('mechanic-attendance.destroy', $attendance), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->assertDatabaseHas('mechanic_attendances', [
            'id' => $attendance->id,
            'deleted_at' => null,
        ]);
    }

    public function test_approved_void_preserves_original_record_and_exposes_it_in_history(): void
    {
        $attendance = $this->attendance();
        $approver = $this->adminApprover();

        $this->actingAs($approver)
            ->delete(route('mechanic-attendance.destroy', $attendance), [
                'void_reason' => 'Duplicate paper attendance entry.',
            ])
            ->assertRedirect();

        $this->assertNull(MechanicAttendance::find($attendance->id));
        $retained = MechanicAttendance::withTrashed()->findOrFail($attendance->id);
        $this->assertSame('Duplicate paper attendance entry.', $retained->void_reason);
        $this->assertSame($approver->id, $retained->voided_by);
        $this->assertNotNull($retained->deleted_at);
        $this->assertSame('08:00', substr((string) $retained->time_in, 0, 5));

        $this->get(route('mechanic-attendance', ['attendance_date' => '2026-11-17']))
            ->assertOk()
            ->assertDontSee('Audit Mechanic');

        $this->get(route('mechanic-attendance', ['records' => 'voided', 'attendance_date' => '2026-11-17']))
            ->assertOk()
            ->assertSee('Audit Mechanic')
            ->assertSee('Duplicate paper attendance entry.');
    }

    public function test_user_without_approve_permission_cannot_void_attendance(): void
    {
        $attendance = $this->attendance();
        $staff = User::factory()->create([
            'department' => 'Maintenance',
            'role' => 'staff',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $this->actingAs($staff)
            ->delete(route('mechanic-attendance.destroy', $attendance), [
                'void_reason' => 'Incorrect entry must be corrected.',
            ])
            ->assertForbidden();

        $this->assertNull($attendance->fresh()->deleted_at);
    }
}

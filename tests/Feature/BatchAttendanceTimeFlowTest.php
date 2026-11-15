<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use App\Models\Operation\Driver;
use App\Models\Operation\DriverAttendance;
use App\Models\Operation\Mechanic;
use App\Models\Operation\MechanicAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchAttendanceTimeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_attendance_persists_time_boundaries_and_reloads_them_for_both_personnel_types(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        $drivers = collect([
            ['D-0600', 'Six AM Driver', 'Morning', '06:00'],
            ['D-0730', 'Seven Thirty Driver', 'Morning', '07:30'],
            ['D-1430', 'Two Thirty PM Driver', 'Afternoon', '14:30'],
            ['D-0000', 'Midnight Driver', 'Night', '00:00'],
            ['D-1200', 'Noon Driver', 'Morning', '12:00'],
        ])->map(function (array $data): array {
            Driver::create([
                'driver_id' => $data[0],
                'driver_name' => $data[1],
                'shift' => $data[2],
                'employment_status' => 'Active',
            ]);

            return [
                'person_id' => $data[0],
                'name' => $data[1],
                'shift' => $data[2],
                'time_in' => $data[3],
                'time_out' => null,
                'status' => 'Present',
                'assigned_job' => null,
            ];
        })->all();

        $this->actingAs($user)
            ->postJson(route('operation.attendance.batch.store', ['type' => 'driver']), [
                'attendance_date' => '2026-10-08',
                'rows' => $drivers,
            ])
            ->assertOk()
            ->assertJsonPath('saved', 5);

        $expectedDriverStatuses = [
            'D-0600' => 'Present',
            'D-0730' => 'Late',
            'D-1430' => 'Late',
            'D-0000' => 'Present',
            'D-1200' => 'Late',
        ];

        foreach ($drivers as $row) {
            $attendance = DriverAttendance::query()
                ->where('driver_id', $row['person_id'])
                ->whereDate('attendance_date', '2026-10-08')
                ->firstOrFail();

            $this->assertSame($row['time_in'].':00', $attendance->time_in);
            $this->assertSame($expectedDriverStatuses[$row['person_id']], $attendance->status);
        }

        Mechanic::create([
            'mechanic_id' => 'M-0730',
            'mechanic_name' => 'Seven Thirty Mechanic',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $this->actingAs($user)
            ->postJson(route('operation.attendance.batch.store', ['type' => 'mechanic']), [
                'attendance_date' => '2026-10-08',
                'rows' => [[
                    'person_id' => 'M-0730',
                    'name' => 'Seven Thirty Mechanic',
                    'shift' => 'Morning',
                    'time_in' => '07:30',
                    'time_out' => null,
                    'status' => 'Present',
                    'assigned_job' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('saved', 1);

        $mechanicAttendance = MechanicAttendance::query()
            ->where('mechanic_id', 'M-0730')
            ->whereDate('attendance_date', '2026-10-08')
            ->firstOrFail();
        $this->assertSame('07:30:00', $mechanicAttendance->time_in);
        $this->assertSame('Late', $mechanicAttendance->status);

        $this->actingAs($user)
            ->getJson(route('operation.attendance.batch.roster', [
                'type' => 'driver',
                'date' => '2026-10-08',
                'shift' => 'all',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'person_id' => 'D-1430',
                'time_in' => '14:30',
                'status' => 'Late',
            ])
            ->assertJsonFragment([
                'person_id' => 'D-0000',
                'time_in' => '00:00',
                'status' => 'Present',
            ]);
    }

    public function test_shared_picker_has_one_confirmation_path_and_accepts_valid_24_hour_values(): void
    {
        $batchJs = file_get_contents(resource_path('js/Operation/Attendance/batch-attendance.js'));
        $pickerJs = file_get_contents(resource_path('js/Main-js/date-time-picker.js'));

        $this->assertSame(1, substr_count($batchJs, "addEventListener('gct:time-selected'"));
        $this->assertStringContainsString(
            'if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time))',
            $batchJs
        );
        $this->assertStringNotContainsString('gct:batch-shared-time', $batchJs);
        $this->assertStringNotContainsString('sharedTimeClickListener', $batchJs);
        $this->assertStringContainsString(
            "input.dispatchEvent(new CustomEvent('gct:time-selected'",
            $pickerJs
        );
        $this->assertStringNotContainsString('gct:batch-shared-time', $pickerJs);
    }
}

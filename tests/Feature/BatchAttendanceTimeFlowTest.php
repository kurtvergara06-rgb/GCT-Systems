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
            ['D-0000', 'Midnight Driver', 'Morning', '00:00'],
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
            'D-0730' => 'Present',
            'D-1430' => 'Present',
            'D-0000' => 'Present',
            'D-1200' => 'Present',
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
        // 07:30 is late under the configured mechanic attendance boundary.
        $this->assertSame('Late', $mechanicAttendance->status);

        $this->actingAs($user)
            ->getJson(route('operation.attendance.batch.roster', [
                'type' => 'driver',
                'date' => '2026-10-08',
                'shift' => 'all',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'rows');
    }

    public function test_saving_selected_driver_attendance_keeps_other_drivers_available_for_next_batch(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
        ]);

        foreach (['D-PART-A' => 'Partial Driver A', 'D-PART-B' => 'Partial Driver B', 'D-PART-C' => 'Partial Driver C'] as $id => $name) {
            Driver::create([
                'driver_id' => $id,
                'driver_name' => $name,
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]);
        }

        $url = route('operation.attendance.batch.store', ['type' => 'driver']);
        $roster = route('operation.attendance.batch.roster', ['type' => 'driver', 'date' => '2026-10-20', 'shift' => 'all']);

        $this->actingAs($user)->postJson($url, [
            'attendance_date' => '2026-10-20',
            'rows' => [[
                'person_id' => 'D-PART-A', 'name' => 'Partial Driver A', 'shift' => 'Morning',
                'time_in' => '06:00', 'status' => 'Present',
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);

        $response = $this->actingAs($user)->getJson($roster)->assertOk();
        $rows = collect($response->json('rows'))->keyBy('person_id');

        $this->assertFalse($rows->has('D-PART-A'));
        $this->assertFalse($rows->get('D-PART-B')['recorded']);
        $this->assertSame('Unmarked', $rows->get('D-PART-B')['status']);
        $this->assertFalse($rows->get('D-PART-C')['recorded']);

        $this->actingAs($user)->postJson($url, [
            'attendance_date' => '2026-10-20',
            'rows' => [[
                'person_id' => 'D-PART-B', 'name' => 'Partial Driver B', 'shift' => 'Morning',
                'time_in' => '07:00', 'status' => 'Present',
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->assertSame(1, DriverAttendance::where('driver_id', 'D-PART-A')->whereDate('attendance_date', '2026-10-20')->count());
        $this->assertSame(1, DriverAttendance::where('driver_id', 'D-PART-B')->whereDate('attendance_date', '2026-10-20')->count());
        $this->assertSame(0, DriverAttendance::where('driver_id', 'D-PART-C')->whereDate('attendance_date', '2026-10-20')->count());

        $this->actingAs($user)->postJson($url, [
            'attendance_date' => '2026-10-20',
            'rows' => [[
                'person_id' => 'D-PART-A', 'name' => 'Partial Driver A', 'shift' => 'Morning',
                'time_in' => '08:00', 'status' => 'Present',
            ]],
        ])->assertUnprocessable();
    }

    public function test_unrecorded_driver_is_reported_as_unmarked_but_present_driver_stays_hidden(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation', 'role' => 'head', 'status' => 'Active',
        ]);
        Driver::create(['driver_id' => 'D-UNMARKED', 'driver_name' => 'Unmarked QA Driver', 'shift' => 'Morning', 'employment_status' => 'Active']);
        Driver::create(['driver_id' => 'D-RECORDED', 'driver_name' => 'Recorded QA Driver', 'shift' => 'Morning', 'employment_status' => 'Active']);

        DriverAttendance::create([
            'driver_id' => 'D-RECORDED', 'driver_name' => 'Recorded QA Driver',
            'shift' => 'Morning', 'attendance_date' => '2026-10-20',
            'status' => 'Present', 'time_in' => '06:00:00',
        ]);

        $roster = $this->actingAs($user)->getJson(route('operation.attendance.batch.roster', [
            'type' => 'driver', 'date' => '2026-10-20', 'shift' => 'all',
        ]))->assertOk();
        $rows = collect($roster->json('rows'))->keyBy('person_id');
        $this->assertTrue($rows->has('D-UNMARKED'));
        $this->assertFalse($rows->get('D-UNMARKED')['recorded']);
        $this->assertSame('Unmarked', $rows->get('D-UNMARKED')['status']);
        $this->assertFalse($rows->has('D-RECORDED'));

        $this->actingAs($user)->postJson(route('operation.attendance.batch.store', ['type' => 'driver']), [
            'attendance_date' => '2026-10-20',
            'rows' => [[
                'person_id' => 'D-UNMARKED', 'name' => 'Unmarked QA Driver',
                'shift' => 'Morning', 'status' => 'Present', 'time_in' => '07:00',
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->assertSame('Present', DriverAttendance::where('driver_id', 'D-UNMARKED')->firstOrFail()->status);
        $this->assertSame(1, DriverAttendance::where('driver_id', 'D-UNMARKED')->count());
        $this->actingAs($user)->getJson(route('operation.attendance.batch.roster', [
            'type' => 'driver', 'date' => '2026-10-20', 'shift' => 'all',
        ]))->assertOk()->assertJsonCount(0, 'rows');
    }

    public function test_driver_roster_is_a_json_list_of_only_unrecorded_drivers_and_partial_save_isolated(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation', 'role' => 'head', 'status' => 'Active',
        ]);

        foreach ([
            'D-REC-PRESENT' => 'Recorded Present Driver',
            'D-REC-ABSENT' => 'Recorded Absent Driver',
            'D-REC-LEAVE' => 'Recorded Leave Driver',
            'D-UNMARKED-LIST' => 'Selectable Unmarked Driver',
            'D-NO-RECORD' => 'No Record Driver',
        ] as $id => $name) {
            Driver::create([
                'driver_id' => $id,
                'driver_name' => $name,
                'shift' => 'Morning',
                'employment_status' => 'Active',
            ]);
        }

        foreach ([
            'D-REC-PRESENT' => 'Present',
            'D-REC-ABSENT' => 'Absent',
            'D-REC-LEAVE' => 'On Leave',
        ] as $id => $status) {
            DriverAttendance::create([
                'driver_id' => $id,
                'driver_name' => Driver::where('driver_id', $id)->value('driver_name'),
                'shift' => 'Morning',
                'attendance_date' => '2026-10-20',
                'status' => $status,
                'time_in' => $status === 'Present' ? '06:00:00' : null,
            ]);
        }

        $rosterUrl = route('operation.attendance.batch.roster', [
            'type' => 'driver', 'date' => '2026-10-20', 'shift' => 'all',
        ]);
        $roster = $this->actingAs($user)->getJson($rosterUrl)->assertOk();
        $payload = json_decode($roster->getContent());

        $this->assertIsArray($payload->rows, 'The frontend requires rows to be encoded as a JSON array.');
        $this->assertSame(
            ['D-NO-RECORD', 'D-UNMARKED-LIST'],
            collect($payload->rows)->pluck('person_id')->sort()->values()->all()
        );
        $this->assertSame(['Unmarked'], collect($payload->rows)->pluck('status')->unique()->values()->all());

        $this->actingAs($user)->postJson(route('operation.attendance.batch.store', ['type' => 'driver']), [
            'attendance_date' => '2026-10-20',
            'rows' => [[
                'person_id' => 'D-UNMARKED-LIST',
                'name' => 'Selectable Unmarked Driver',
                'shift' => 'Morning',
                'status' => 'Present',
                'time_in' => '07:00',
            ]],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->assertDatabaseMissing('driver_attendances', [
            'driver_id' => 'D-NO-RECORD',
            'attendance_date' => '2026-10-20',
        ]);
        $this->actingAs($user)->getJson($rosterUrl)
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.person_id', 'D-NO-RECORD');
    }

    public function test_batch_attendance_respects_admin_controlled_operation_permissions(): void
    {
        $staff = User::factory()->create([
            'department' => 'Operation',
            'role' => 'staff',
            'status' => 'Active',
        ]);

        $role = \App\Models\Admin\RolePermission::query()
            ->where('role_key', 'operation_staff')->firstOrFail();
        $original = $role->permissions;
        $restricted = $original;
        data_set($restricted, 'operation.view', false);
        data_set($restricted, 'operation.edit', false);
        $role->update(['permissions' => $restricted]);

        $url = route('operation.attendance.batch.roster', [
            'type' => 'driver', 'date' => '2026-10-20',
        ]);
        $store = route('operation.attendance.batch.store', ['type' => 'driver']);

        $this->actingAs($staff)->getJson($url)->assertForbidden();
        $this->actingAs($staff)->postJson($store, [
            'attendance_date' => '2026-10-20', 'rows' => [],
        ])->assertForbidden();

        data_set($restricted, 'operation.view', true);
        $role->update(['permissions' => $restricted]);
        $this->actingAs($staff)->getJson($url)->assertOk();
        $this->actingAs($staff)->postJson($store, [
            'attendance_date' => '2026-10-20', 'rows' => [],
        ])->assertForbidden();

        data_set($restricted, 'operation.edit', true);
        $role->update(['permissions' => $restricted]);
        $this->actingAs($staff)->postJson($store, [
            'attendance_date' => '2026-10-20', 'rows' => [],
        ])->assertUnprocessable();
    }

    public function test_batch_attendance_rejects_unsupported_night_shift_rows(): void
    {
        $user = User::factory()->create([
            'department' => 'Operation',
            'role' => 'head',
            'status' => 'Active',
            'must_change_password' => false,
            'onboarding_completed' => true,
        ]);

        Driver::create([
            'driver_id' => 'D-NIGHT',
            'driver_name' => 'Unsupported Night Driver',
            'shift' => 'Morning',
            'employment_status' => 'Active',
        ]);

        $this->actingAs($user)
            ->postJson(route('operation.attendance.batch.store', ['type' => 'driver']), [
                'attendance_date' => '2026-10-08',
                'rows' => [[
                    'person_id' => 'D-NIGHT',
                    'name' => 'Unsupported Night Driver',
                    'shift' => 'Night',
                    'time_in' => '00:00',
                    'time_out' => null,
                    'status' => 'Present',
                    'assigned_job' => null,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows.0.shift');

        $this->assertDatabaseMissing('driver_attendances', [
            'driver_id' => 'D-NIGHT',
            'attendance_date' => '2026-10-08',
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

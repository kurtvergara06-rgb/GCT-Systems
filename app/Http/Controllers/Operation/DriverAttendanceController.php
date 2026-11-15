<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Operation\DriverAttendance;
use App\Traits\SystemDataUpdateBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Throwable;

class DriverAttendanceController extends Controller
{
    use SystemDataUpdateBroadcaster;

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = DriverAttendance::query();

        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('driver_id', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('shift', 'like', "%{$search}%")
                    ->orWhere('bus_assignment', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled('status')
            && $request->status !== 'All Status'
        ) {
            $query->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TABLE DATA
        |--------------------------------------------------------------------------
        */

        $driverAttendances = $query
            ->latest()
            ->paginate(8)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | SUMMARY CARDS
        |--------------------------------------------------------------------------
        */

        $present = DriverAttendance::where(
            'status',
            'Present'
        )->count();

        $absent = DriverAttendance::where(
            'status',
            'Absent'
        )->count();

        $late = DriverAttendance::where(
            'status',
            'Late'
        )->count();

        $onDuty = DriverAttendance::where(
            'status',
            'On Duty'
        )->count();

        /*
        |--------------------------------------------------------------------------
        | NEXT DRIVER ID
        |--------------------------------------------------------------------------
        */

        $nextDriverId =
            $this->generateDriverId();

        return view(
            'Operation.Attendance.driver-attendance',
            compact(
                'driverAttendances',
                'present',
                'absent',
                'late',
                'onDuty',
                'nextDriverId'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'bus_assignment' => 'nullable|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $validated['driver_id'] =
            $this->generateDriverId();

        $attendance =
            DriverAttendance::create(
                $validated
            );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'created',
            $attendance->id,
            'A driver attendance record was created.'
        );

        session()->flash(
            'success',
            'Driver attendance record created successfully.'
        );

        return new RedirectResponse('/driver-attendance');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        DriverAttendance $driverAttendance
    ): RedirectResponse {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:255',
            'shift' => 'required|string|max:255',
            'bus_assignment' => 'nullable|string|max:255',
            'attendance_date' => 'required|date',
            'time_in' => 'nullable',
            'time_out' => 'nullable',
            'status' => 'required|string|in:Present,Late,Absent,On Leave,On Duty',
        ]);

        $driverAttendance->update(
            $validated
        );

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'updated',
            $driverAttendance->id,
            'A driver attendance record was updated.'
        );

        session()->flash(
            'success',
            'Driver attendance record updated successfully.'
        );

        return new RedirectResponse('/driver-attendance');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(
        DriverAttendance $driverAttendance
    ): RedirectResponse {
        $attendanceId =
            $driverAttendance->id;

        $driverAttendance->delete();

        $this->broadcastSystemDataUpdated(
            'Operation',
            'Attendance',
            'deleted',
            $attendanceId,
            'A driver attendance record was deleted.'
        );

        session()->flash(
            'success',
            'Driver attendance record deleted successfully.'
        );

        return new RedirectResponse('/driver-attendance');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT CSV
    |--------------------------------------------------------------------------
    */

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' =>
                'required|file|mimes:csv,txt',
        ]);

        $file =
            $request->file(
                'import_file'
            );

        $handle =
            fopen(
                $file->getRealPath(),
                'r'
            );

        if (! $handle) {
            session()->flash(
                'error',
                'Unable to read the uploaded CSV file.'
            );

            return new RedirectResponse('/driver-attendance');
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | READ HEADER
            |--------------------------------------------------------------------------
            */

            $header =
                fgetcsv($handle);

            if (! $header) {
                session()->flash(
                    'error',
                    'CSV file is empty.'
                );

                return new RedirectResponse('/driver-attendance');
            }

            /*
            |--------------------------------------------------------------------------
            | NORMALIZE HEADER
            |--------------------------------------------------------------------------
            */

            $header = array_map(function ($value) {

                $value = trim((string) $value);

                // Remove UTF-8 BOM from the first CSV header
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

                return strtolower($value);

            }, $header);

            /*
            |--------------------------------------------------------------------------
            | REQUIRED COLUMNS
            |--------------------------------------------------------------------------
            */

            $requiredColumns = [
                'driver_name',
                'shift',
                'bus_assignment',
                'attendance_date',
                'time_in',
                'time_out',
                'status',
            ];

            foreach (
                $requiredColumns as $column
            ) {
                if (
                    ! in_array(
                        $column,
                        $header,
                        true
                    )
                ) {
                    session()->flash(
                        'error',
                        "Invalid CSV format. Missing required column: {$column}"
                    );

                    return new RedirectResponse('/driver-attendance');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORT DATA
            |--------------------------------------------------------------------------
            */

            $imported = 0;
            $skipped = 0;
            $rowNumber = 1;

            while (
                ($row = fgetcsv($handle))
                !== false
            ) {
                $rowNumber++;

                /*
                |--------------------------------------------------------------------------
                | SKIP EMPTY ROW
                |--------------------------------------------------------------------------
                */

                if (
                    count(
                        array_filter(
                            $row,
                            fn ($value) =>
                                trim(
                                    (string) $value
                                ) !== ''
                        )
                    ) === 0
                ) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | COLUMN COUNT CHECK
                |--------------------------------------------------------------------------
                */

                if (
                    count($row)
                    !== count($header)
                ) {
                    $skipped++;

                    continue;
                }

                $data =
                    array_combine(
                        $header,
                        $row
                    );

                if (
                    ! $data
                    || empty(
                        trim(
                            $data['driver_name']
                            ?? ''
                        )
                    )
                ) {
                    $skipped++;

                    continue;
                }

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */

                    $status =
                        trim(
                            $data['status']
                            ?? 'Present'
                        );

                    if (
                        ! in_array(
                            $status,
                            [
                                'Present',
                                'Late',
                                'Absent',
                                'On Leave',
                                'On Duty',
                            ],
                            true
                        )
                    ) {
                        $status = 'Present';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DATE
                    |--------------------------------------------------------------------------
                    */

                    $attendanceDate =
                        $this->parseCsvDate(
                            $data[
                                'attendance_date'
                            ] ?? null
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | TIME
                    |--------------------------------------------------------------------------
                    */

                    $timeIn =
                        $this->parseCsvTime(
                            $data[
                                'time_in'
                            ] ?? null
                        );

                    $timeOut =
                        $this->parseCsvTime(
                            $data[
                                'time_out'
                            ] ?? null
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE RECORD
                    |--------------------------------------------------------------------------
                    */

                    DriverAttendance::create([
                        'driver_id' =>
                            $this->generateDriverId(),

                        'driver_name' =>
                            trim(
                                $data[
                                    'driver_name'
                                ]
                            ),

                        'shift' =>
                            trim(
                                $data[
                                    'shift'
                                ] ?? 'Morning'
                            ),

                        'bus_assignment' =>
                            trim(
                                $data[
                                    'bus_assignment'
                                ] ?? ''
                            ),

                        'attendance_date' =>
                            $attendanceDate,

                        'time_in' =>
                            $timeIn,

                        'time_out' =>
                            $timeOut,

                        'status' =>
                            $status,
                    ]);

                    $imported++;

                } catch (
                    Throwable $error
                ) {
                    $skipped++;

                    session()->flash(
                        'error',
                        "Invalid CSV format on row {$rowNumber}. "
                        . "Use date YYYY-MM-DD and time HH:MM:SS or 08:00 AM."
                    );

                    return new RedirectResponse('/driver-attendance');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NO DATA IMPORTED
            |--------------------------------------------------------------------------
            */

            if ($imported === 0) {
                session()->flash(
                    'error',
                    'No driver attendance records were imported. Please check your CSV format.'
                );

                return new RedirectResponse('/driver-attendance');
            }

            /*
            |--------------------------------------------------------------------------
            | BROADCAST UPDATE
            |--------------------------------------------------------------------------
            */

            $this->broadcastSystemDataUpdated(
                'Operation',
                'Attendance',
                'updated',
                null,
                "{$imported} driver attendance record(s) imported successfully."
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS MESSAGE
            |--------------------------------------------------------------------------
            */

            $message =
                "{$imported} driver attendance record(s) imported successfully.";

            if ($skipped > 0) {
                $message .=
                    " {$skipped} row(s) were skipped.";
            }

            session()->flash(
                'success',
                $message
            );

            return new RedirectResponse('/driver-attendance');

        } catch (
            Throwable $error
        ) {

            session()->flash(
                'error',
                'Unable to import the CSV file. Please check the file format and try again.'
            );

            return new RedirectResponse('/driver-attendance');

        } finally {

            if (
                is_resource($handle)
            ) {
                fclose($handle);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARSE CSV DATE
    |--------------------------------------------------------------------------
    */

    private function parseCsvDate(
        ?string $date
    ): string {
        $date =
            trim(
                $date ?? ''
            );

        if ($date === '') {
            return now()
                ->format('Y-m-d');
        }

        $formats = [
            'Y-m-d',
            'm/d/Y',
            'm/d/y',
            'd/m/Y',
        ];

        foreach (
            $formats as $format
        ) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $date
                )->format('Y-m-d');

            } catch (
                Throwable $error
            ) {
                continue;
            }
        }

        throw new \Exception(
            'Invalid attendance date.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PARSE CSV TIME
    |--------------------------------------------------------------------------
    */

    private function parseCsvTime(
        ?string $time
    ): ?string {
        $time =
            trim(
                $time ?? ''
            );

        if ($time === '') {
            return null;
        }

        $formats = [
            'H:i:s',
            'H:i',
            'h:i A',
            'h:i:s A',
        ];

        foreach (
            $formats as $format
        ) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $time
                )->format('H:i:s');

            } catch (
                Throwable $error
            ) {
                continue;
            }
        }

        throw new \Exception(
            'Invalid time format.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE DRIVER ID
    |--------------------------------------------------------------------------
    */

    private function generateDriverId(): string
    {
        $year =
            now()->format('Y');

        $lastDriverAttendance =
            DriverAttendance::where(
                'driver_id',
                'like',
                "D-{$year}-%"
            )
                ->orderByDesc('id')
                ->first();

        if (! $lastDriverAttendance) {
            return "D-{$year}-0001";
        }

        preg_match(
            '/D-' . $year . '-(\d+)/',
            $lastDriverAttendance
                ->driver_id,
            $matches
        );

        $lastNumber =
            isset($matches[1])
                ? (int) $matches[1]
                : 0;

        $nextNumber =
            $lastNumber + 1;

        $newDriverId =
            'D-'
            . $year
            . '-'
            . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );

        while (
            DriverAttendance::where(
                'driver_id',
                $newDriverId
            )->exists()
        ) {
            $nextNumber++;

            $newDriverId =
                'D-'
                . $year
                . '-'
                . str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
        }

        return $newDriverId;
    }
}
<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FuelReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = FuelReport::query()
            ->with(['gpsTripRecord', 'bus']);

        if ($request->filled('search')) {
            $search = trim($request->string('search'));

            $query->where(function ($q) use ($search) {
                $q->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('distance_source', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_filter')) {
            match ($request->date_filter) {
                'Today' => $query->whereDate('report_date', today()),

                'This Week' => $query->whereBetween('report_date', [
                    now()->startOfWeek()->toDateString(),
                    now()->endOfWeek()->toDateString(),
                ]),

                'This Month' => $query
                    ->whereMonth('report_date', now()->month)
                    ->whereYear('report_date', now()->year),

                default => null,
            };
        } else {
            $query
                ->whereMonth('report_date', now()->month)
                ->whereYear('report_date', now()->year);
        }

        $fuelReports = $query
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();

        $totalFuelUsed = (float) $fuelReports->sum('fuel_liters');
        $totalDistance = (float) $fuelReports->sum('distance_km');

        $fleetAverage = $totalFuelUsed > 0
            ? $totalDistance / $totalFuelUsed
            : 0;

        $vehicleSummaries = $fuelReports
            ->groupBy('bus_no')
            ->map(function ($records, $busNo) use ($fleetAverage) {
                $totalKm = (float) $records->sum('distance_km');
                $totalLiters = (float) $records->sum('fuel_liters');

                $kmPerLiter = $totalLiters > 0
                    ? $totalKm / $totalLiters
                    : 0;

                $vsFleetAvg = $fleetAverage > 0
                    ? (($kmPerLiter - $fleetAverage) / $fleetAverage) * 100
                    : 0;

                return (object) [
                    'bus_no' => $busNo,
                    'total_km' => $totalKm,
                    'total_liters' => $totalLiters,
                    'km_per_liter' => $kmPerLiter,
                    'vs_fleet_avg' => $vsFleetAvg,
                    'entries' => $records->count(),
                    'status' => $this->getFuelStatus($kmPerLiter),
                ];
            })
            ->sortByDesc('km_per_liter')
            ->values();

        $inefficientVehicles = $vehicleSummaries
            ->where('status', 'Inefficient')
            ->count();

        $mostEfficientVehicle = $vehicleSummaries
            ->sortByDesc('km_per_liter')
            ->first();

        $leastEfficientVehicle = $vehicleSummaries
            ->filter(fn ($vehicle) => $vehicle->km_per_liter > 0)
            ->sortBy('km_per_liter')
            ->first();

        $recentFuelRecords = $fuelReports
            ->take(5)
            ->values();

        $buses = Bus::query()
            ->orderBy('bus_no')
            ->get();

        /*
         * Daily Fuel Monitoring is derived from existing Bus Master List,
         * processed GPS data, and existing fuel records. No separate daily
         * monitoring table is required.
         */
        $monitorDate = $request->input(
            'monitor_date',
            today()->toDateString()
        );

        $monitorFilter = $request->input(
            'monitor_filter',
            'all'
        );

        $dailyGpsGroups = GpsTripRecord::query()
            ->whereDate('beginning_at', $monitorDate)
            ->whereNotNull('mileage_km')
            ->where('mileage_km', '>', 0)
            ->whereHas('batchUpload', function ($gpsQuery) {
                $gpsQuery->where('status', 'Processed');
            })
            ->orderBy('beginning_at')
            ->get()
            ->groupBy(fn ($record) => strtoupper(trim($record->bus_no)));

        $dailyFuelGroups = FuelReport::query()
            ->whereDate('report_date', $monitorDate)
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($record) => strtoupper(trim($record->bus_no)));

        $busLookup = $buses->keyBy(
            fn ($bus) => strtoupper(trim($bus->bus_no))
        );

        $monitorBusKeys = $busLookup->keys()
            ->merge($dailyGpsGroups->keys())
            ->merge($dailyFuelGroups->keys())
            ->unique()
            ->sort()
            ->values();

        $allDailyMonitoring = $monitorBusKeys
            ->map(function ($busKey) use (
                $dailyGpsGroups,
                $dailyFuelGroups,
                $busLookup
            ) {
                $gpsRecords = $dailyGpsGroups->get(
                    $busKey,
                    collect()
                );

                $fuelRecord = $dailyFuelGroups
                    ->get($busKey, collect())
                    ->first();

                $distanceKm = (float) $gpsRecords
                    ->sum('mileage_km');

                $idlingMinutes = (int) $gpsRecords
                    ->sum('idling_minutes');

                $bus = $busLookup->get($busKey);

                $workflowStatus = match (true) {
                    $fuelRecord && $distanceKm > 0 => 'Completed',
                    $fuelRecord && $distanceKm <= 0 => 'Missing GPS',
                    $distanceKm > 0 => 'For Fuel Entry',
                    default => 'Monitoring',
                };

                return (object) [
                    'bus_no' => $bus?->bus_no
                        ?? $fuelRecord?->bus_no
                        ?? $gpsRecords->first()?->bus_no
                        ?? $busKey,
                    'bus_model' => $bus?->bus_model,
                    'gps_distance_km' => $distanceKm,
                    'idling_minutes' => $idlingMinutes,
                    'fuel_liters' => (float) ($fuelRecord?->fuel_liters ?? 0),
                    'driver_name' => $fuelRecord?->driver_name,
                    'workflow_status' => $workflowStatus,
                    'efficiency_status' => $fuelRecord?->status ?? 'No Data',
                    'km_per_liter' => (float) ($fuelRecord?->km_per_liter ?? 0),
                ];
            });

        $dailyMonitoringCounts = [
            'total' => $allDailyMonitoring->count(),
            'for_entry' => $allDailyMonitoring
                ->where('workflow_status', 'For Fuel Entry')
                ->count(),
            'completed' => $allDailyMonitoring
                ->where('workflow_status', 'Completed')
                ->count(),
            'needs_review' => $allDailyMonitoring
                ->filter(fn ($row) => in_array(
                    $row->workflow_status,
                    ['Missing GPS', 'Monitoring'],
                    true
                ))
                ->count(),
        ];

        $dailyMonitoring = match ($monitorFilter) {
            'needs-entry' => $allDailyMonitoring
                ->where('workflow_status', 'For Fuel Entry')
                ->values(),
            'needs-review' => $allDailyMonitoring
                ->filter(fn ($row) => in_array(
                    $row->workflow_status,
                    ['Missing GPS', 'Monitoring'],
                    true
                ))
                ->values(),
            'completed' => $allDailyMonitoring
                ->where('workflow_status', 'Completed')
                ->values(),
            default => $allDailyMonitoring->values(),
        };

        return view('Maintenance.fuel-reports', compact(
            'fuelReports',
            'vehicleSummaries',
            'recentFuelRecords',
            'buses',
            'totalFuelUsed',
            'totalDistance',
            'fleetAverage',
            'inefficientVehicles',
            'mostEfficientVehicle',
            'leastEfficientVehicle',
            'dailyMonitoring',
            'dailyMonitoringCounts',
            'monitorDate',
            'monitorFilter'
        ));
    }

    public function gpsDistance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bus_no' => ['required', 'string', 'exists:buses,bus_no'],
            'report_date' => ['required', 'date'],
        ]);

        $gpsRecord = $this->findGpsRecord(
            $validated['bus_no'],
            $validated['report_date']
        );

        if (!$gpsRecord) {
            return response()->json([
                'found' => false,
                'message' => 'No processed GPS mileage was found for this bus and date.',
            ]);
        }

        return response()->json([
            'found' => true,
            'gps_trip_record_id' => $gpsRecord->id,
            'distance_km' => (float) $gpsRecord->mileage_km,
            'beginning_at' => optional($gpsRecord->beginning_at)?->format('M d, Y h:i A'),
            'ending_at' => optional($gpsRecord->ending_at)?->format('M d, Y h:i A'),
            'initial_location' => $gpsRecord->initial_location,
            'final_location' => $gpsRecord->final_location,
            'idling_minutes' => (int) ($gpsRecord->idling_minutes ?? 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->boolean('daily_monitoring')) {
            return $this->storeDailyMonitoring($request);
        }

        $validated = $this->validateFuelReport($request);

        $resolved = $this->resolveDistance($validated);

        if (!$resolved['success']) {
            return back()
                ->withInput()
                ->with('error', $resolved['message']);
        }

        $fuelLiters = (float) $validated['fuel_liters'];
        $distanceKm = (float) $resolved['distance_km'];

        $kmPerLiter = $fuelLiters > 0
            ? $distanceKm / $fuelLiters
            : 0;

        FuelReport::create([
            'report_date' => $validated['report_date'],
            'bus_no' => $validated['bus_no'],
            'driver_name' => $validated['driver_name'] ?? null,
            'gps_trip_record_id' => $resolved['gps_trip_record_id'],
            'distance_km' => $distanceKm,
            'distance_source' => $resolved['distance_source'],
            'fuel_liters' => $fuelLiters,
            'km_per_liter' => round($kmPerLiter, 2),
            'status' => $this->getFuelStatus($kmPerLiter),
            'remarks' => $validated['remarks'] ?? null,
            'manual_distance_reason' => $validated['manual_distance_reason'] ?? null,
        ]);

        return redirect()
            ->to(route('fuel-reports', [], false))
            ->with('success', 'Fuel record saved successfully.');
    }

    public function update(
        Request $request,
        FuelReport $fuelReport
    ): RedirectResponse {
        $validated = $this->validateFuelReport($request);

        $resolved = $this->resolveDistance($validated);

        if (!$resolved['success']) {
            return back()
                ->withInput()
                ->with('error', $resolved['message']);
        }

        $fuelLiters = (float) $validated['fuel_liters'];
        $distanceKm = (float) $resolved['distance_km'];

        $kmPerLiter = $fuelLiters > 0
            ? $distanceKm / $fuelLiters
            : 0;

        $fuelReport->update([
            'report_date' => $validated['report_date'],
            'bus_no' => $validated['bus_no'],
            'driver_name' => $validated['driver_name'] ?? null,
            'gps_trip_record_id' => $resolved['gps_trip_record_id'],
            'distance_km' => $distanceKm,
            'distance_source' => $resolved['distance_source'],
            'fuel_liters' => $fuelLiters,
            'km_per_liter' => round($kmPerLiter, 2),
            'status' => $this->getFuelStatus($kmPerLiter),
            'remarks' => $validated['remarks'] ?? null,
            'manual_distance_reason' => $validated['manual_distance_reason'] ?? null,
        ]);

        return redirect()
            ->to(route('fuel-reports', [], false))
            ->with('success', 'Fuel record updated successfully.');
    }

    public function destroy(FuelReport $fuelReport): RedirectResponse
    {
        $fuelReport->delete();

        return redirect()
            ->to(route('fuel-reports', [], false))
            ->with('success', 'Fuel record deleted successfully.');
    }

    private function storeDailyMonitoring(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monitor_date' => ['required', 'date'],
            'entries' => ['nullable', 'array'],
            'entries.*.bus_no' => [
                'required',
                'string',
                'exists:buses,bus_no',
            ],
            'entries.*.fuel_liters' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'entries.*.driver_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $saved = 0;
        $skipped = 0;

        foreach ($validated['entries'] ?? [] as $entry) {
            if (
                !isset($entry['fuel_liters']) ||
                $entry['fuel_liters'] === '' ||
                (float) $entry['fuel_liters'] <= 0
            ) {
                continue;
            }

            $gpsRecords = GpsTripRecord::query()
                ->whereRaw(
                    'UPPER(TRIM(bus_no)) = ?',
                    [strtoupper(trim($entry['bus_no']))]
                )
                ->whereDate(
                    'beginning_at',
                    $validated['monitor_date']
                )
                ->whereNotNull('mileage_km')
                ->where('mileage_km', '>', 0)
                ->whereHas('batchUpload', function ($gpsQuery) {
                    $gpsQuery->where('status', 'Processed');
                })
                ->orderBy('beginning_at')
                ->get();

            if ($gpsRecords->isEmpty()) {
                $skipped++;
                continue;
            }

            $distanceKm = (float) $gpsRecords->sum('mileage_km');
            $fuelLiters = (float) $entry['fuel_liters'];
            $kmPerLiter = $distanceKm / $fuelLiters;

            $fuelReport = FuelReport::query()
                ->whereDate(
                    'report_date',
                    $validated['monitor_date']
                )
                ->whereRaw(
                    'UPPER(TRIM(bus_no)) = ?',
                    [strtoupper(trim($entry['bus_no']))]
                )
                ->orderByDesc('id')
                ->first();

            $payload = [
                'report_date' => $validated['monitor_date'],
                'bus_no' => $entry['bus_no'],
                'driver_name' => $entry['driver_name'] ?? null,
                'gps_trip_record_id' => $gpsRecords->last()?->id,
                'distance_km' => round($distanceKm, 2),
                'distance_source' => 'GPS',
                'fuel_liters' => round($fuelLiters, 2),
                'km_per_liter' => round($kmPerLiter, 2),
                'status' => $this->getFuelStatus($kmPerLiter),
                'remarks' => 'Saved from Daily Fuel Monitoring.',
                'manual_distance_reason' => null,
            ];

            if ($fuelReport) {
                $fuelReport->update($payload);
            } else {
                FuelReport::create($payload);
            }

            $saved++;
        }

        $message = $saved > 0
            ? "{$saved} daily fuel record(s) saved successfully."
            : 'No fuel entries were changed.';

        if ($skipped > 0) {
            $message .= " {$skipped} row(s) were skipped because GPS data was missing.";
        }

        return redirect()
            ->to(route('fuel-reports', [
                'monitor_date' => $validated['monitor_date'],
            ], false))
            ->with('success', $message);
    }

    private function validateFuelReport(Request $request): array
    {
        return $request->validate([
            'report_date' => ['required', 'date'],
            'bus_no' => ['required', 'string', 'exists:buses,bus_no'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'fuel_liters' => ['required', 'numeric', 'min:0.01'],
            'use_manual_distance' => ['nullable', 'boolean'],
            'distance_km' => ['nullable', 'numeric', 'min:0.01'],
            'manual_distance_reason' => [
                'nullable',
                'required_if:use_manual_distance,1',
                'string',
                'max:1000',
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function resolveDistance(array $validated): array
    {
        $useManualDistance = (bool) ($validated['use_manual_distance'] ?? false);

        if ($useManualDistance) {
            if (
                !isset($validated['distance_km']) ||
                (float) $validated['distance_km'] <= 0
            ) {
                return [
                    'success' => false,
                    'message' => 'Enter a valid manual distance.',
                ];
            }

            return [
                'success' => true,
                'distance_km' => (float) $validated['distance_km'],
                'distance_source' => 'Manual',
                'gps_trip_record_id' => null,
            ];
        }

        $gpsRecord = $this->findGpsRecord(
            $validated['bus_no'],
            $validated['report_date']
        );

        if (!$gpsRecord) {
            return [
                'success' => false,
                'message' => 'No processed GPS mileage was found for this bus and date. Upload the GPS Mileage Report or use manual distance.',
            ];
        }

        return [
            'success' => true,
            'distance_km' => (float) $gpsRecord->mileage_km,
            'distance_source' => 'GPS',
            'gps_trip_record_id' => $gpsRecord->id,
        ];
    }

    private function findGpsRecord(
        string $busNo,
        string $reportDate
    ): ?GpsTripRecord {
        return GpsTripRecord::query()
            ->whereRaw(
                'UPPER(TRIM(bus_no)) = ?',
                [strtoupper(trim($busNo))]
            )
            ->whereDate('beginning_at', $reportDate)
            ->whereNotNull('mileage_km')
            ->where('mileage_km', '>', 0)
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();
    }

    private function getFuelStatus(float $kmPerLiter): string
    {
        return match (true) {
            $kmPerLiter >= 6 => 'Efficient',
            $kmPerLiter >= 4 => 'Normal',
            $kmPerLiter > 0 => 'Inefficient',
            default => 'No Data',
        };
    }
}

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
            ->take(10)
            ->values();

        $buses = Bus::query()
            ->orderBy('bus_no')
            ->get();

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
            'leastEfficientVehicle'
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
            ->route('fuel-reports')
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
            ->route('fuel-reports')
            ->with('success', 'Fuel record updated successfully.');
    }

    public function destroy(FuelReport $fuelReport): RedirectResponse
    {
        $fuelReport->delete();

        return redirect()
            ->route('fuel-reports')
            ->with('success', 'Fuel record deleted successfully.');
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
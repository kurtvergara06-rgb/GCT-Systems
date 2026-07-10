<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use App\Models\Maintenance\FuelReport;
use Illuminate\Http\Request;

class FuelReportController extends Controller
{
    public function index(Request $request)
    {
        $query = FuelReport::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_filter')) {
            if ($request->date_filter === 'Today') {
                $query->whereDate('report_date', today());
            }

            if ($request->date_filter === 'This Week') {
                $query->whereBetween('report_date', [
                    now()->startOfWeek()->toDateString(),
                    now()->endOfWeek()->toDateString(),
                ]);
            }

            if ($request->date_filter === 'This Month') {
                $query->whereMonth('report_date', now()->month)
                    ->whereYear('report_date', now()->year);
            }
        }

        $fuelReports = $query
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->get();

        $totalFuelUsed = $fuelReports->sum('fuel_liters');
        $totalDistance = $fuelReports->sum('distance_km');

        $fleetAverage = $totalFuelUsed > 0
            ? $totalDistance / $totalFuelUsed
            : 0;

        $vehicleSummaries = $fuelReports
            ->groupBy('bus_no')
            ->map(function ($records, $busNo) use ($fleetAverage) {
                $totalKm = $records->sum('distance_km');
                $totalLiters = $records->sum('fuel_liters');

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
            ->sortBy('bus_no')
            ->values();

        $inefficientVehicles = $vehicleSummaries
            ->where('status', 'Inefficient')
            ->count();

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
            'inefficientVehicles'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'bus_no' => ['required', 'string', 'exists:buses,bus_no'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'fuel_liters' => ['required', 'numeric', 'min:0.01'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $distanceKm = $validated['distance_km'] ?? null;

        if ($distanceKm === null || $distanceKm === '') {
            $distanceKm = $this->getGpsDistanceKm(
                $validated['bus_no'],
                $validated['report_date']
            );
        }

        if ($distanceKm === null) {
            return redirect()
                ->route('fuel-reports')
                ->with('error', 'No GPS mileage was found for this bus. Enter distance manually or upload GPS Mileage Report first.');
        }

        $distanceKm = (float) $distanceKm;
        $fuelLiters = (float) $validated['fuel_liters'];

        $kmPerLiter = $fuelLiters > 0
            ? $distanceKm / $fuelLiters
            : 0;

        $status = $this->getFuelStatus($kmPerLiter);

        FuelReport::create([
            'report_date' => $validated['report_date'],
            'bus_no' => $validated['bus_no'],
            'driver_name' => $validated['driver_name'] ?? null,
            'distance_km' => $distanceKm,
            'fuel_liters' => $fuelLiters,
            'km_per_liter' => round($kmPerLiter, 2),
            'status' => $status,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return redirect()
            ->route('fuel-reports')
            ->with('success', 'Fuel record saved successfully.');
    }

    private function getGpsDistanceKm(string $busNo, string $reportDate): ?float
    {
        $gpsRecord = GpsTripRecord::query()
            ->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($busNo))])
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->whereDate('beginning_at', $reportDate)
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();

        if ($gpsRecord) {
            return (float) $gpsRecord->mileage_km;
        }

        $latestGpsRecord = GpsTripRecord::query()
            ->whereRaw('UPPER(TRIM(bus_no)) = ?', [strtoupper(trim($busNo))])
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->first();

        if ($latestGpsRecord) {
            return (float) $latestGpsRecord->mileage_km;
        }

        return null;
    }

    private function getFuelStatus(float $kmPerLiter): string
    {
        if ($kmPerLiter >= 4) {
            return 'Efficient';
        }

        if ($kmPerLiter >= 3) {
            return 'Normal';
        }

        return 'Inefficient';
    }
}
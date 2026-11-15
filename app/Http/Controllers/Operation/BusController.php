<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\Admin\GpsTripRecord;
use App\Models\Maintenance\Bus;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function index(Request $request)
    {
        $query = Bus::query();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('bus_no', 'like', "%{$search}%")
                    ->orWhere('plate_no', 'like', "%{$search}%")
                    ->orWhere('bus_model', 'like', "%{$search}%")
                    ->orWhere('route_grouping', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (
            $request->filled('status') &&
            $request->status !== 'All Status'
        ) {
            $query->where('status', $request->status);
        }

        $buses = $query
            ->orderBy('bus_no')
            ->paginate(10)
            ->withQueryString();

        $gpsRecords = GpsTripRecord::query()
            ->whereNotNull('bus_no')
            ->whereNotNull('mileage_km')
            ->whereHas('batchUpload', function ($query) {
                $query->where('status', 'Processed');
            })
            ->orderByDesc('beginning_at')
            ->orderByDesc('id')
            ->get();

        $gpsByBus = $gpsRecords
            ->groupBy(function ($record) {
                return strtoupper(trim($record->bus_no));
            })
            ->map(function ($records) {
                $latest = $records->first();

                return [
                    'latest_gps_km' => (float) $latest->mileage_km,
                    'latest_gps_at' => $latest->beginning_at ?? $latest->created_at,
                ];
            });

        $buses->getCollection()->transform(function (Bus $bus) use ($gpsByBus) {
            $gps = $gpsByBus->get(strtoupper(trim($bus->bus_no)));

            $bus->display_latest_gps_km = $gps['latest_gps_km'] ?? null;
            $bus->display_latest_gps_at = $gps['latest_gps_at'] ?? null;

            return $bus;
        });

        $totalBuses = Bus::count();
        $activeBuses = Bus::where('status', 'Active')->count();
        $underMaintenance = Bus::where('status', 'Under Maintenance')->count();

        $withGpsData = Bus::query()
            ->get()
            ->filter(function (Bus $bus) use ($gpsByBus) {
                return $gpsByBus->has(strtoupper(trim($bus->bus_no)));
            })
            ->count();

        return view('Operation.bus-master-list', compact(
            'buses',
            'totalBuses',
            'activeBuses',
            'underMaintenance',
            'withGpsData'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_no' => 'required|string|max:100|unique:buses,bus_no',
            'plate_no' => 'nullable|string|max:100',
            'bus_model' => 'nullable|string|max:255',
            'year_model' => 'nullable|string|max:20',
            'capacity' => 'nullable|integer|min:1',
            'route_grouping' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Inactive,Under Maintenance',
            'last_pms_km' => 'nullable|numeric|min:0',
            'pms_interval_km' => 'nullable|numeric|min:1',
        ]);

        $lastPmsKm = (float) ($validated['last_pms_km'] ?? 0);
        $pmsIntervalKm = (float) ($validated['pms_interval_km'] ?? 5000);

        Bus::create([
            'bus_no' => strtoupper(trim($validated['bus_no'])),
            'plate_no' => $validated['plate_no'] ?? null,
            'bus_model' => $validated['bus_model'] ?? null,
            'year_model' => $validated['year_model'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'route_grouping' => $validated['route_grouping'] ?? null,
            'status' => $validated['status'],
            'last_pms_km' => $lastPmsKm,
            'pms_interval_km' => $pmsIntervalKm,
            'next_pms_km' => $lastPmsKm + $pmsIntervalKm,
        ]);

        return redirect()
            ->route('bus-master-list')
            ->with('success', 'Bus added successfully.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');

        if (! $file || ! $file->isValid()) {
            return redirect()
                ->back()
                ->with('error', 'Please upload a valid CSV file.');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            return redirect()
                ->back()
                ->with('error', 'Unable to read the CSV file.');
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return redirect()
                ->back()
                ->with('error', 'The CSV file is empty.');
        }

        $header = array_map(function ($value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);

            return strtolower(trim($value));
        }, $header);

        if (! in_array('bus_no', $header, true)) {
            fclose($handle);

            return redirect()
                ->back()
                ->with('error', 'CSV must have a bus_no column.');
        }

        $added = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_pad($row, count($header), null);

            $data = array_combine(
                $header,
                array_slice($row, 0, count($header))
            );

            $busNo = strtoupper(trim($data['bus_no'] ?? ''));

            if ($busNo === '') {
                $skipped++;
                continue;
            }

            $lastPmsKm = is_numeric($data['last_pms_km'] ?? null)
                ? (float) $data['last_pms_km']
                : 0;

            $pmsIntervalKm = is_numeric($data['pms_interval_km'] ?? null)
                ? (float) $data['pms_interval_km']
                : 5000;

            $status = trim($data['status'] ?? '');

            if (! in_array($status, ['Active', 'Inactive', 'Under Maintenance'], true)) {
                $status = 'Active';
            }

            $busData = [
                'plate_no' => trim($data['plate_no'] ?? '') ?: null,
                'bus_model' => trim($data['bus_model'] ?? '') ?: null,
                'year_model' => trim($data['year_model'] ?? '') ?: null,
                'capacity' => is_numeric($data['capacity'] ?? null)
                    ? (int) $data['capacity']
                    : null,
                'route_grouping' => trim($data['route_grouping'] ?? '') ?: null,
                'status' => $status,
                'last_pms_km' => $lastPmsKm,
                'pms_interval_km' => $pmsIntervalKm,
                'next_pms_km' => $lastPmsKm + $pmsIntervalKm,
            ];

            $existingBus = Bus::where('bus_no', $busNo)->first();

            if ($existingBus) {
                $existingBus->update($busData);
                $updated++;
            } else {
                Bus::create(array_merge([
                    'bus_no' => $busNo,
                ], $busData));

                $added++;
            }
        }

        fclose($handle);

        return redirect()
            ->route('bus-master-list')
            ->with(
                'success',
                "CSV imported. Added: {$added}, Updated: {$updated}, Skipped: {$skipped}."
            );
    }

    public function update(Request $request, Bus $bus)
    {
        $validated = $request->validate([
            'bus_no' => 'required|string|max:100|unique:buses,bus_no,' . $bus->id,
            'plate_no' => 'nullable|string|max:100',
            'bus_model' => 'nullable|string|max:255',
            'year_model' => 'nullable|string|max:20',
            'capacity' => 'nullable|integer|min:1',
            'route_grouping' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Inactive,Under Maintenance',
            'last_pms_km' => 'nullable|numeric|min:0',
            'pms_interval_km' => 'nullable|numeric|min:1',
        ]);

        $lastPmsKm = (float) ($validated['last_pms_km'] ?? 0);
        $pmsIntervalKm = (float) ($validated['pms_interval_km'] ?? 5000);

        $bus->update([
            'bus_no' => strtoupper(trim($validated['bus_no'])),
            'plate_no' => $validated['plate_no'] ?? null,
            'bus_model' => $validated['bus_model'] ?? null,
            'year_model' => $validated['year_model'] ?? null,
            'capacity' => $validated['capacity'] ?? null,
            'route_grouping' => $validated['route_grouping'] ?? null,
            'status' => $validated['status'],
            'last_pms_km' => $lastPmsKm,
            'pms_interval_km' => $pmsIntervalKm,
            'next_pms_km' => $lastPmsKm + $pmsIntervalKm,
        ]);

        return redirect()
            ->route('bus-master-list')
            ->with('success', 'Bus information updated successfully.');
    }

    public function destroy(Bus $bus)
    {
        $bus->delete();

        return redirect()
            ->route('bus-master-list')
            ->with('success', 'Bus deleted successfully.');
    }
}
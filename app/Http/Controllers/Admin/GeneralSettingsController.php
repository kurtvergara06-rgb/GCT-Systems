<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSetting;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GeneralSettingsController extends Controller
{
    private function canEdit(Request $request): bool
    {
        $user = $request->user();
        $role = str_replace(['_', '-'], ' ', strtolower(trim((string) $user->role)));
        return $role === 'system admin'
            || (strtolower(trim((string) $user->department)) === 'admin' && $role === 'head');
    }

    public function index(Request $request)
    {
        return view('Admin.Settings.general-settings', [
            'settings' => SystemSetting::current(),
            'canEdit' => $this->canEdit($request),
        ]);
    }

    public function update(Request $request, ActivityLogService $audit)
    {
        abort_unless($this->canEdit($request), 403);
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_email' => ['nullable', 'email', 'max:254'],
            'organization_phone' => ['nullable', 'string', 'max:50'],
            'organization_address' => ['nullable', 'string', 'max:1000'],
            'organization_logo' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ]);

        DB::transaction(function () use ($request, $data, $audit) {
            $settings = SystemSetting::current();
            $settings->id = 1;
            $settings->fill(\Illuminate\Support\Arr::except($data, ['organization_logo']));
            if ($request->hasFile('organization_logo')) {
                $logo = $request->file('organization_logo');
                $settings->organization_logo = 'data:'.$logo->getMimeType().';base64,'.base64_encode($logo->get());
            }
            $settings->updated_by_name = $request->user()->name;
            $settings->save();
            $audit->record($request->user(), $request, 'Updated General Settings',
                'Updated', 'Admin', 'General Settings', 'Organization configuration updated.');
        });

        return redirect()->route('admin.settings.general')->with('success', 'General settings saved.');
    }

    public function services(BatchFileProcessingController $batch, \App\Services\SystemServiceHealth $health)
    {
        $statuses = Cache::remember('general-settings.service-status.v2', 30, function () use ($batch, $health) {
            $python = 'Unavailable';
            try {
                $response = Http::connectTimeout(2)->timeout(3)
                    ->get(rtrim((string) config('services.nlp.api_url'), '/').'/health');
                if ($response->successful() && $response->json('service') === 'GCT Python Engine') {
                    $python = 'Reachable';
                }
            } catch (\Throwable) {}
            try {
                $nlp = ucfirst((string) ($batch->nlpStatus()->getData(true)['status'] ?? 'unknown'));
            } catch (\Throwable) {
                $nlp = 'Unknown';
            }
            try {
                DB::select('SELECT 1');
                $database = 'Connected';
            } catch (\Throwable) {
                $database = 'Unavailable';
            }
            return ['python' => $python, 'nlp' => $nlp, 'database' => $database,
                'reverb' => $health->reverb(), 'email' => $health->email(),
                'checked_at' => now()->toIso8601String()];
        });
        return response()->json($statuses + [
            'scheduler' => $health->scheduler(),
        ])->header('Cache-Control', 'no-store');
    }
}

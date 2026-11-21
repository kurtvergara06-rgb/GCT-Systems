<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemSetting;
use App\Services\ActivityLogService;
use App\Services\SecurityPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecuritySettingsController extends Controller
{
    private function canEdit(Request $request): bool
    {
        $user = $request->user();
        $role = str_replace(['_', '-'], ' ', strtolower(trim((string) $user?->role)));
        return $role === 'system admin'
            || (strtolower(trim((string) $user?->department)) === 'admin' && $role === 'head');
    }

    public function index(Request $request): View
    {
        return view('Admin.Settings.security-settings', [
            'policy' => SecurityPolicy::current(),
            'canEdit' => $this->canEdit($request),
        ]);
    }

    public function update(Request $request, ActivityLogService $audit): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        $data = $request->validate([
            'minimum_password_length' => ['required', 'integer', Rule::in([8, 10, 12])],
            'password_expiration' => ['required', 'integer', Rule::in([0, 30, 90, 180])],
            'session_timeout' => ['required', 'integer', Rule::in([0, 30, 60, 120, 240])],
            'failed_attempts' => ['required', 'integer', Rule::in([3, 5, 7, 10])],
            'lockout_duration' => ['required', 'integer', Rule::in([5, 15, 30, 60])],
            'require_uppercase' => ['sometimes', 'boolean'],
            'require_number' => ['sometimes', 'boolean'],
            'require_special' => ['sometimes', 'boolean'],
            'allow_multiple_sessions' => ['sometimes', 'boolean'],
            'logout_on_password_change' => ['sometimes', 'boolean'],
        ]);
        // A database policy cannot lengthen the underlying Laravel session TTL.
        $serverLifetime = max(1, (int) config('session.lifetime', 120));
        if ($data['session_timeout'] > $serverLifetime) {
            return back()->withInput()->withErrors([
                'session_timeout' => 'The selected timeout exceeds the server session lifetime ('.$serverLifetime.' minutes).',
            ]);
        }
        foreach (['require_uppercase', 'require_number', 'require_special', 'allow_multiple_sessions', 'logout_on_password_change'] as $key) {
            $data[$key] = $request->boolean($key);
        }
        DB::transaction(function () use ($data, $request, $audit): void {
            $settings = SystemSetting::query()->lockForUpdate()->find(1);
            if (! $settings) {
                $settings = new SystemSetting();
                $settings->id = 1;
                $settings->organization_name = 'GCT Transport Services, Inc.';
            }
            $settings->security_policy = $data;
            $settings->updated_by_name = (string) $request->user()->name;
            $settings->save();
            $audit->record($request->user(), $request, 'Updated Security Settings',
                'Updated', 'Admin', 'Security Settings',
                'Security configuration updated by an authorized administrator.');
        });
        SecurityPolicy::clearCache();
        return redirect()->route('admin.settings.security')->with('success', 'Security settings saved successfully.');
    }
}

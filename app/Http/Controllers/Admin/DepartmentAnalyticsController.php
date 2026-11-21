<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DepartmentAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentAnalyticsController extends Controller
{
    public function show(Request $request, string $stage, DepartmentAnalyticsService $analytics): View|RedirectResponse
    {
        abort_unless(in_array($stage, ['descriptive', 'diagnostic', 'predictive', 'prescriptive'], true), 404);
        $user = $request->user();
        abort_unless($user && $user->hasSystemPermission('analytics', 'view'), 403);
        $capability = match ($stage) {
            'descriptive' => 'view',
            'diagnostic' => 'diagnostic',
            'predictive' => 'predictive',
            'prescriptive' => 'recommendations',
        };
        abort_unless($user->hasSystemPermission('analytics', $capability), 403);
        $department = strtolower(trim((string) $user->department));
        abort_unless(in_array($department, ['operation', 'maintenance', 'warehouse', 'purchase'], true), 403);
        $dates = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $dates['from'] ?? now()->startOfMonth()->subMonths(5)->toDateString();
        $to = $dates['to'] ?? now()->toDateString();
        abort_if($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366, 422, 'Choose a date range of at most one year.');

        // Legacy department descriptive links now open the actual shared Admin view.
        if ($stage === 'descriptive') {
            return redirect()->route('analytics.descriptive', ['from' => $from, 'to' => $to]);
        }

        return redirect()->route('analytics.stage', ['stage' => $stage] + $request->only(['period', 'bus', 'from', 'to', 'issue', 'category']));
    }
}

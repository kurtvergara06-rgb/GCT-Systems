<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('Onboarding.welcome', [
            'user' => $user,
            'tips' => $this->tipsFor($user),
            'dashboardUrl' => $this->dashboardUrlFor($user),
            'replay' => $request->boolean('replay'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return redirect()
            ->route('onboarding.show')
            ->with('success', 'Profile details saved.');
    }

    public function complete(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect($this->dashboardUrlFor($user))
            ->with('success', 'Welcome to GCT. Your account setup is complete.');
    }

    public function skip(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ])->save();

        return redirect($this->dashboardUrlFor($user))
            ->with('success', 'Tutorial skipped. You can replay it anytime from Account Settings.');
    }

    private function tipsFor(User $user): array
    {
        $department = $this->normalize($user->department);

        return match ($department) {
            'maintenance' => [
                ['Maintenance Referrals', 'Review breakdown referrals from Operation before creating repair work.'],
                ['Job Orders', 'Assign mechanics, track repair progress, and request parts when needed.'],
                ['PMS Scheduling', 'Monitor preventive maintenance and convert due schedules into Job Orders.'],
                ['Purchase Requests', 'Track parts from request through warehouse issue and completion.'],
            ],
            'operation', 'operations' => [
                ['Trip Scheduling', 'Plan trips and assign drivers and buses before dispatch.'],
                ['Daily Driver Reports', 'Record actual trip activity and operational performance.'],
                ['Incidents', 'Report traffic, breakdowns, and road incidents as they happen.'],
                ['Maintenance Referral', 'Operation Heads can refer Bus Breakdown incidents to Maintenance.'],
            ],
            'warehouse' => [
                ['Inventory', 'Monitor on-hand quantities, reorder levels, and stock availability.'],
                ['Part Requests', 'Review approved requests and issue parts to Maintenance.'],
                ['Incoming Deliveries', 'Receive purchased parts and post inventory updates.'],
                ['Stock Movements', 'Audit stock-in and stock-out activity across the warehouse.'],
            ],
            'purchase', 'purchasing' => [
                ['Requested Purchase', 'Review maintenance and inventory requests ready for procurement.'],
                ['Purchase Orders', 'Create and track supplier orders through delivery or pickup.'],
                ['Scheduled Purchase', 'Manage recurring or planned purchasing requirements.'],
                ['Delivery Flow', 'Keep order statuses current so Warehouse and Maintenance stay synchronized.'],
            ],
            'admin', 'administration' => [
                ['Accounts', 'Create users, assign departments and roles, and manage account status.'],
                ['Roles & Permissions', 'Control what each department role can view or change.'],
                ['Analytics', 'Review descriptive, diagnostic, predictive, and prescriptive insights.'],
                ['Activity Logs', 'Audit important system actions and account activity.'],
            ],
            default => [
                ['Dashboard', 'Use your department dashboard to see your current work and alerts.'],
                ['Notifications', 'Check the top bar for pending actions and recent updates.'],
                ['Account Settings', 'Update your password and replay this tutorial at any time.'],
            ],
        };
    }

    private function dashboardUrlFor(User $user): string
    {
        return match ($this->normalize($user->department)) {
            'maintenance' => route('maintenance-dashboard', [], false),
            'operation', 'operations' => route('dashboard-operation', [], false),
            'warehouse' => route('warehouse.dashboard', [], false),
            'purchase', 'purchasing' => route('purchase-orders', [], false),
            'admin', 'administration' => route('admin.dashboard', [], false),
            default => route('account.profile', [], false),
        };
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim(str_replace(['_', '-'], ' ', (string) $value)));
    }
}

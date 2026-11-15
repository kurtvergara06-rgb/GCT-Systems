<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DataActivity;
use Illuminate\Http\JsonResponse;

class TransferActivityController extends Controller
{
    public function recent(): JsonResponse
    {
        $activities = DataActivity::query()
            ->where(function ($query) {
                $query->where('activity_type', 'Import')
                    ->orWhere(function ($exportQuery) {
                        $exportQuery->where('activity_type', 'Export')
                            ->where('total_records', '>', 0);
                    });
            })
            ->latest()
            ->limit(6)
            ->get(['id', 'file_name', 'activity_type', 'module', 'status']);

        return response()->json([
            'activities' => $activities->map(fn (DataActivity $activity) => [
                'id' => $activity->id,
                'file_name' => $activity->file_name,
                'activity_type' => $activity->activity_type,
                'module' => $activity->module,
                'status' => $activity->status,
            ])->values(),
        ]);
    }
}

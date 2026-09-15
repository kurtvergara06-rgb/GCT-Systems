<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DataActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = DataActivity::query()
            ->with('processor')
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('file_name', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%")
                    ->orWhere('data_type', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && $request->query('type') !== 'All Types') {
            $query->where('activity_type', $request->query('type'));
        }

        if ($request->filled('module') && $request->query('module') !== 'All Modules') {
            $query->where('module', $request->query('module'));
        }

        if ($request->filled('status') && $request->query('status') !== 'All Status') {
            $query->where('status', $request->query('status'));
        }

        $history = $query
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => DataActivity::count(),
            'successful' => DataActivity::where('status', 'Completed')->count(),
            'processed_files' => DataActivity::whereIn('activity_type', [
                'Batch Processing',
                'Import',
            ])->where('status', 'Completed')->count(),
            'failed' => DataActivity::whereIn('status', ['Failed', 'Needs Correction'])->count(),
        ];

        return view('Admin.Data_Management.data-history', compact('history', 'stats'));
    }

    public function show(DataActivity $activity): JsonResponse
    {
        $activity->load('processor');
        $details = $activity->details ?? [];
        $validationErrors = $details['validation_errors'] ?? [];

        // Build remediation URL if applicable
        $remediationUrl = null;
        if ($activity->reference_type === 'batch_upload') {
            $remediationUrl = route('admin.batch-file-processing');
        } elseif ($activity->activity_type === 'Import') {
            $remediationUrl = route('admin.import-export');
        }

        return response()->json([
            'id' => $activity->id,
            'file_name' => $activity->file_name ?: 'System Data Activity',
            'activity_type' => $activity->activity_type,
            'module' => $activity->module ?: '—',
            'data_type' => $activity->data_type ?: '—',
            'source' => $activity->source ?: '—',
            'status' => $activity->status,
            'total_records' => $activity->total_records,
            'successful_records' => $activity->successful_records,
            'failed_records' => $activity->failed_records,
            'skipped_records' => $activity->skipped_records,
            'processed_by' => $activity->processor?->name ?? 'System',
            'created_at' => $activity->created_at?->format('M d, Y g:i A'),
            'completed_at' => $activity->completed_at?->format('M d, Y g:i A') ?? '—',
            'error_message' => $activity->error_message,
            'validation_errors' => is_array($validationErrors) ? array_slice($validationErrors, 0, 50) : [],
            'details' => $details,
            'remediation_url' => $remediationUrl,
        ]);
    }
}

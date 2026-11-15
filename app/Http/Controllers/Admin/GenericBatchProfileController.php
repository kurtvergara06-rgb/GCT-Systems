<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BatchUpload;
use Illuminate\Http\JsonResponse;

class GenericBatchProfileController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $profiles = BatchUpload::query()
            ->where('data_type', '!=', 'GPS Trip Records')
            ->get(['id', 'module', 'data_type', 'status'])
            ->mapWithKeys(function (BatchUpload $batch) {
                return [
                    (string) $batch->id => [
                        'module' => $batch->module,
                        'data_type' => $batch->data_type,
                        'status' => $batch->status,
                        'review_url' => route(
                            'batch-file-processing.generic.review',
                            $batch,
                            false
                        ),
                        'structured_url' => route(
                            'batch-file-processing',
                            ['batch_id' => $batch->id],
                            false
                        ),
                    ],
                ];
            });

        return response()->json([
            'profiles' => $profiles,
        ]);
    }
}

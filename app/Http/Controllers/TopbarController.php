<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use App\Services\TopbarSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TopbarController extends Controller
{
    public function summary(
        Request $request,
        TopbarSummaryService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json($service->summary($user));
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        DB::table('topbar_read_states')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [
                'notifications_read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}

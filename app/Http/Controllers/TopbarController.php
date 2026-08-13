<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use App\Services\TopbarSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $userId = (int) $request->user()->id;
        $readAt = now();

        DB::transaction(function () use ($userId, $readAt): void {
            DB::table('topbar_read_states')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'notifications_read_at' => $readAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if (Schema::hasTable('topbar_notification_reads')) {
                DB::table('topbar_notification_reads')
                    ->where('user_id', $userId)
                    ->delete();
            }
        });

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}

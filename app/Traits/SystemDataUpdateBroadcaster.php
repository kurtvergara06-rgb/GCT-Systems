<?php

namespace App\Traits;

use App\Events\SystemDataUpdated;
use App\Models\TopbarNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait SystemDataUpdateBroadcaster
{
    protected function broadcastSystemDataUpdated(
        string $module,
        string $entity,
        string $action,
        mixed $recordId,
        string $message
    ): void {
        try {
            if (Schema::hasTable('topbar_notifications')) {
                TopbarNotification::create([
                    'module' => $module,
                    'entity' => $entity,
                    'action' => $action,
                    'record_id' => $recordId,
                    'message' => $message,
                    'created_by' => auth()->id(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Topbar notification could not be stored.', [
                'module' => $module,
                'entity' => $entity,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            Log::info('Broadcasting SystemDataUpdated event', [
                'module' => $module,
                'entity' => $entity,
                'action' => $action,
                'record_id' => $recordId,
                'message' => $message,
            ]);

            event(new SystemDataUpdated(
                $module,
                $entity,
                $action,
                $recordId,
                $message
            ));
        } catch (Throwable $exception) {
            Log::warning('SystemDataUpdated broadcast failed', [
                'module' => $module,
                'entity' => $entity,
                'action' => $action,
                'record_id' => $recordId,
                'message' => $message,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

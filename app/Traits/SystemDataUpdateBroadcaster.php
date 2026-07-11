<?php

namespace App\Traits;

use App\Events\SystemDataUpdated;
use Illuminate\Support\Facades\Log;
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
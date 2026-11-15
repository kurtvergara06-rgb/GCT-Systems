<?php

namespace App\Traits;

use App\Events\SystemDataUpdated;
use Illuminate\Support\Facades\Log;

trait SystemDataUpdateBroadcaster
{
    protected function broadcastSystemDataUpdated(string $module, string $entity, string $action, $record_id, string $message): void
    {
        Log::info('Broadcasting SystemDataUpdated event', [
            'module' => $module,
            'entity' => $entity,
            'action' => $action,
            'record_id' => $record_id,
            'message' => $message,
        ]);

        event(new SystemDataUpdated($module, $entity, $action, $record_id, $message));
    }
}

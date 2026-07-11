<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemDataUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $module;
    public string $entity;
    public string $action;
    public mixed $record_id;
    public string $message;

    public function __construct(
        string $module,
        string $entity,
        string $action,
        mixed $record_id,
        string $message
    ) {
        $this->module = $module;
        $this->entity = $entity;
        $this->action = $action;
        $this->record_id = $record_id;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('system-updates'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'module' => $this->module,
            'entity' => $this->entity,
            'action' => $this->action,
            'record_id' => $this->record_id,
            'message' => $this->message,
        ];
    }

    public function broadcastAs(): string
    {
        return 'SystemDataUpdated';
    }
}
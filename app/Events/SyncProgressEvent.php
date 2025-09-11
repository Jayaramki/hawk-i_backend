<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncProgressEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $integrationType;
    public $syncType;
    public $progress;

    /**
     * Create a new event instance.
     */
    public function __construct(string $integrationType, string $syncType, array $progress)
    {
        $this->integrationType = $integrationType; // e.g., 'bamboohr', 'azure_devops', 'jira'
        $this->syncType = $syncType; // e.g., 'directory', 'detailed', 'full'
        $this->progress = $progress;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sync-progress'),
            new PrivateChannel('sync-progress.' . $this->integrationType),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sync.progress';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'integration_type' => $this->integrationType,
            'sync_type' => $this->syncType,
            'progress' => $this->progress,
            'timestamp' => now()->toISOString(),
        ];
    }
}

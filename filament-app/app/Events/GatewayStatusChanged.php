<?php

namespace App\Events;

use App\Models\Gateway;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GatewayStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Gateway $gateway,
        public string $previousStatus,
        public string $currentStatus
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('gateways'),
            new Channel('gateway.' . $this->gateway->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'gateway' => [
                'id' => $this->gateway->id,
                'name' => $this->gateway->name,
                'ip_address' => $this->gateway->ip_address,
                'port' => $this->gateway->port,
                'is_active' => $this->gateway->is_active,
                'last_seen_at' => $this->gateway->last_seen_at?->toISOString(),
                'success_count' => $this->gateway->success_count,
                'failure_count' => $this->gateway->failure_count,
            ],
            'status_change' => [
                'previous' => $this->previousStatus,
                'current' => $this->currentStatus,
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'gateway.status-changed';
    }
}
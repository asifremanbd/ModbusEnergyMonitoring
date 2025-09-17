<?php

namespace App\Events;

use App\Models\Reading;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewReadingReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Reading $reading
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('readings'),
            new Channel('gateway.' . $this->reading->dataPoint->gateway_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'reading' => [
                'id' => $this->reading->id,
                'data_point_id' => $this->reading->data_point_id,
                'scaled_value' => $this->reading->scaled_value,
                'quality' => $this->reading->quality,
                'read_at' => $this->reading->read_at->toISOString(),
                'data_point' => [
                    'id' => $this->reading->dataPoint->id,
                    'gateway_id' => $this->reading->dataPoint->gateway_id,
                    'group_name' => $this->reading->dataPoint->group_name,
                    'label' => $this->reading->dataPoint->label,
                ],
            ],
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'reading.new';
    }
}
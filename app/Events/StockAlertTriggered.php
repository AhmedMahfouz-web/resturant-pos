<?php

namespace App\Events;

use App\Models\StockAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;

    /**
     * Create a new event instance.
     */
    public function __construct(StockAlert $alert)
    {
        $this->alert = $alert;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('stock-alerts'),
            new Channel('inventory'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alert->id,
            'material_id' => $this->alert->material_id,
            'material_name' => $this->alert->material->name,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'current_quantity' => $this->alert->current_quantity,
            'threshold_quantity' => $this->alert->threshold_quantity,
            'message' => $this->alert->message,
            'created_at' => $this->alert->created_at->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stock-alert.triggered';
    }
}

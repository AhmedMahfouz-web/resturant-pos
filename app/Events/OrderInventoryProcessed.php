<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderInventoryProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $consumptionData;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, array $consumptionData)
    {
        $this->order = $order;
        $this->consumptionData = $consumptionData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
            new Channel('inventory'),
            new Channel('order.' . $this->order->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_code' => $this->order->code,
            'order_status' => $this->order->status,
            'consumption_summary' => [
                'total_items' => count($this->consumptionData),
                'total_cost' => collect($this->consumptionData)->sum('total_cost'),
                'materials_affected' => collect($this->consumptionData)
                    ->pluck('materials')
                    ->flatten(1)
                    ->pluck('material_id')
                    ->unique()
                    ->count()
            ],
            'consumption_details' => $this->consumptionData,
            'processed_at' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.inventory-processed';
    }
}

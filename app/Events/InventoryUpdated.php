<?php

namespace App\Events;

use App\Models\Material;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $material;
    public $changeType;
    public $changeData;
    public $previousQuantity;
    public $newQuantity;

    /**
     * Create a new event instance.
     */
    public function __construct(Material $material, string $changeType, array $changeData = [])
    {
        $this->material = $material;
        $this->changeType = $changeType;
        $this->changeData = $changeData;
        $this->previousQuantity = $changeData['previous_quantity'] ?? null;
        $this->newQuantity = $material->quantity;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('inventory'),
            new Channel('inventory.material.' . $this->material->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'material_id' => $this->material->id,
            'material_name' => $this->material->name,
            'change_type' => $this->changeType,
            'previous_quantity' => $this->previousQuantity,
            'new_quantity' => $this->newQuantity,
            'stock_unit' => $this->material->stock_unit,
            'reorder_point' => $this->material->reorder_point,
            'is_low_stock' => $this->newQuantity <= $this->material->reorder_point,
            'change_data' => $this->changeData,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'inventory.updated';
    }
}

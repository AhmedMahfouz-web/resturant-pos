<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class OrderEvents implements ShouldBroadcast  // Make sure this is here
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct($order)
    {
        $this->order = $order->load('orderItems.product');
    }

    public function broadcastOn(): array
    {
        return ['orders-channel'];
    }

    public function broadcastAs(): string
    {
        return 'order-updated';
    }
}

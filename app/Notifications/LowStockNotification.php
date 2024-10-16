<?php

namespace App\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $product;
    protected $material;

    public function __construct($product, $material)
    {
        $this->product = $product;
        $this->material = $material;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Low stock for product: ' . $this->product->name . '. Material ' . $this->material->name . ' is below required threshold.',
            'product_id' => $this->product->id,
            'material_id' => $this->material->id,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'message' => 'Low stock for product: ' . $this->product->name . '. Material ' . $this->material->name . ' is below required threshold.',
            'product_id' => $this->product->id,
            'material_id' => $this->material->id,
        ]);
    }

    public function broadcastOn()
    {
        return new Channel('low-stock'); // Channel used for broadcasting
    }
}

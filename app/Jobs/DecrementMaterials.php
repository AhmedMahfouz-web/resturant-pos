<?php

namespace App\Jobs;

use App\Http\Controllers\MaterialController;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DecrementMaterials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $material = app(MaterialController::class);
        foreach ($this->order->orderItems as $item) {
            $product = $item->product;

            // Decrement the material quantity
            $material->decrementMaterials($product, $item->quantity);
        }
    }
}

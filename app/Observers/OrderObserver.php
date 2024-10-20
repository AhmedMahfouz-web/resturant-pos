<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Table;

class OrderObserver
{
    public function created(Order $order): void
    {
        // Update table's free status
        $table = Table::find($order->table_id);
        $table->update(['is_free' => 0]);   
    }

    public function updated(Order $order): void
    {
        // Update table's free status
        if ($order->status == 'completed' || $order->status == 'canceled') {
            $table = Table::find($order->table_id);
            $table->update(['is_free' => 1]);
        }
    }
}

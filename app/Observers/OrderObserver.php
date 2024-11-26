<?php

namespace App\Observers;

use App\Events\OrderCreated;
use App\Events\OrderEvents;
use App\Models\Order;
use App\Models\Table;

class OrderObserver
{
    public function created(Order $order): void
    {
        // Update table's free status
        if (!empty($order->table_id)) {
            $table = Table::find($order->table_id);
            $table->update(['is_free' => 0]);
        }

        event(new OrderCreated($order));
    }

    public function updated(Order $order): void
    {
        // updateOrderTotals($order->id);
        // Update table's free status
        if ($order->status == 'completed' || $order->status == 'canceled') {
            if ($order->type == 'dine-in' && !empty($order->table_id)) {
                $table = Table::find($order->table_id);
                $table->update(['is_free' => 1]);
            }
        } else {
            if ($order->type == 'dine-in' && !empty($order->table_id)) {
                $table = Table::find($order->table_id);
                $table->update(['is_free' => 0]);
            }
        }

        event(new OrderEvents($order));
    }
}

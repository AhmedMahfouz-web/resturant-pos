<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'quantity' => $this->quantity,
            'stock_unit' => $this->stock_unit,
            'recipe_unit' => $this->recipe_unit,
            'conversion_rate' => $this->conversion_rate,
            'purchase_price' => $this->purchase_price,
            'minimum_stock_level' => $this->minimum_stock_level,
            'maximum_stock_level' => $this->maximum_stock_level,
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'storage_location' => $this->storage_location,
            'is_perishable' => $this->is_perishable,
            'shelf_life_days' => $this->shelf_life_days,

            // Relationships
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name
                ];
            }),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name
                ];
            }),

            // Calculated fields
            'current_stock_value' => $this->getCurrentStockValue(),
            'is_low_stock' => $this->isBelowMinimumStock(),
            'is_at_reorder_point' => $this->isAtReorderPoint(),
            'is_overstock' => $this->isAboveMaximumStock(),

            // Stock batches
            'stock_batches' => $this->whenLoaded('stockBatches', function () {
                return $this->stockBatches->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'remaining_quantity' => $batch->remaining_quantity,
                        'unit_cost' => $batch->unit_cost,
                        'total_value' => $batch->total_value,
                        'expiry_date' => $batch->expiry_date?->toDateString(),
                        'days_until_expiry' => $batch->days_until_expiry,
                        'is_expiring' => $batch->is_expiring,
                        'is_expired' => $batch->is_expired
                    ];
                });
            }),

            // Active alerts
            'active_alerts' => $this->whenLoaded('stockAlerts', function () {
                return $this->stockAlerts->map(function ($alert) {
                    return [
                        'id' => $alert->id,
                        'alert_type' => $alert->alert_type,
                        'priority' => $alert->priority,
                        'message' => $alert->message,
                        'created_at' => $alert->created_at->toISOString()
                    ];
                });
            }),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }
}

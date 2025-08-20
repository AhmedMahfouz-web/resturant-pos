<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'material_id' => $this->material_id,
            'material_name' => $this->whenLoaded('material', $this->material->name),
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->whenLoaded('supplier', $this->supplier?->name),

            // Quantities
            'quantity' => $this->quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'stock_unit' => $this->whenLoaded('material', $this->material->stock_unit),

            // Costs and values
            'unit_cost' => $this->unit_cost,
            'total_value' => $this->total_value,
            'usage_percentage' => $this->usage_percentage,

            // Dates
            'received_date' => $this->received_date->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $this->days_until_expiry,

            // Status flags
            'is_expired' => $this->is_expired,
            'is_expiring' => $this->is_expiring,
            'is_available' => $this->isAvailable(),
            'is_fully_consumed' => $this->isFullyConsumed(),

            // Urgency level for expiring batches
            'urgency_level' => $this->when($this->expiry_date, function () {
                if ($this->is_expired) return 'expired';
                if ($this->days_until_expiry <= 2) return 'critical';
                if ($this->days_until_expiry <= 7) return 'high';
                if ($this->days_until_expiry <= 14) return 'medium';
                return 'low';
            }),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }
}

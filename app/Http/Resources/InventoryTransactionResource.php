<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'material_name' => $this->whenLoaded('material', $this->material->name),
            'stock_unit' => $this->whenLoaded('material', $this->material->stock_unit),

            // Transaction details
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->quantity * $this->unit_cost,

            // User and notes
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', $this->user?->name),
            'notes' => $this->notes,

            // Transaction type display
            'type_display' => $this->getTypeDisplay(),
            'quantity_display' => $this->getQuantityDisplay(),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get display name for transaction type
     */
    private function getTypeDisplay(): string
    {
        return match ($this->type) {
            'receipt' => 'Material Receipt',
            'consumption' => 'Stock Consumption',
            'adjustment' => 'Stock Adjustment',
            'transfer' => 'Stock Transfer',
            'waste' => 'Waste/Loss',
            default => ucfirst($this->type)
        };
    }

    /**
     * Get formatted quantity display
     */
    private function getQuantityDisplay(): string
    {
        $sign = $this->quantity >= 0 ? '+' : '';
        $unit = $this->whenLoaded('material', $this->material->stock_unit, '');
        return $sign . $this->quantity . ' ' . $unit;
    }
}

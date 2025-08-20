<?php

namespace App\Events;

use App\Models\Recipe;
use App\Models\RecipeCostCalculation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecipeCostUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $recipe;
    public $costCalculation;
    public $previousCost;

    /**
     * Create a new event instance.
     */
    public function __construct(Recipe $recipe, RecipeCostCalculation $costCalculation, $previousCost = null)
    {
        $this->recipe = $recipe;
        $this->costCalculation = $costCalculation;
        $this->previousCost = $previousCost;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('recipe-costs'),
            new Channel('recipe-costs.recipe.' . $this->recipe->id),
            new Channel('inventory'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $costChange = null;
        $percentageChange = null;

        if ($this->previousCost !== null) {
            $costChange = $this->costCalculation->total_cost - $this->previousCost;
            $percentageChange = $this->previousCost > 0 ?
                ($costChange / $this->previousCost) * 100 : 0;
        }

        return [
            'recipe_id' => $this->recipe->id,
            'recipe_name' => $this->recipe->name,
            'calculation_id' => $this->costCalculation->id,
            'calculation_method' => $this->costCalculation->calculation_method,
            'total_cost' => $this->costCalculation->total_cost,
            'cost_per_serving' => $this->costCalculation->cost_per_serving,
            'previous_cost' => $this->previousCost,
            'cost_change' => $costChange,
            'percentage_change' => $percentageChange,
            'calculated_at' => $this->costCalculation->calculation_date->toISOString()
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'recipe-cost.updated';
    }
}

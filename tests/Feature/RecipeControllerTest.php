<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and authenticate
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_get_recipes_list()
    {
        // Create some recipes
        Recipe::factory()->count(3)->create();

        $response = $this->getJson('/api/recipes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'instructions',
                            'current_cost',
                            'fifo_cost',
                            'can_be_prepared'
                        ]
                    ]
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_show_specific_recipe()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        $response = $this->getJson("/api/recipes/{$recipe->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'instructions',
                    'current_cost',
                    'fifo_cost',
                    'can_be_prepared',
                    'insufficient_materials'
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_calculate_recipe_cost()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        // Create a material and attach it to the recipe
        $material = Material::factory()->create([
            'purchase_price' => 2.50,
            'recipe_unit' => 'kg'
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2.0]);

        $response = $this->postJson("/api/recipes/{$recipe->id}/calculate-cost", [
            'method' => 'purchase_price',
            'save_calculation' => true
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recipe_id',
                    'recipe_name',
                    'method',
                    'breakdown' => [
                        'total_cost',
                        'materials',
                        'calculated_at'
                    ],
                    'calculation'
                ],
                'message'
            ]);

        // Verify the calculation was saved
        $this->assertDatabaseHas('recipe_cost_calculations', [
            'recipe_id' => $recipe->id,
            'calculation_method' => 'purchase_price',
            'total_cost' => 5.00
        ]);
    }

    /** @test */
    public function it_can_compare_recipe_costs()
    {
        $recipe = Recipe::factory()->create(['name' => 'Test Recipe']);

        // Create a material and attach it to the recipe
        $material = Material::factory()->create([
            'purchase_price' => 2.50,
            'recipe_unit' => 'kg',
            'stock_unit' => 'kg',
            'conversion_rate' => 1.0,
            'quantity' => 10.0
        ]);

        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 2.0]);

        $response = $this->getJson("/api/recipes/{$recipe->id}/compare-costs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recipe_id',
                    'recipe_name',
                    'fifo_method' => [
                        'total_cost',
                        'method'
                    ],
                    'purchase_price_method' => [
                        'total_cost',
                        'method'
                    ],
                    'difference',
                    'percentage_difference',
                    'can_be_prepared',
                    'insufficient_materials'
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_can_get_unpreparable_recipes()
    {
        // Create a recipe with insufficient materials
        $recipe = Recipe::factory()->create(['name' => 'Unpreparable Recipe']);

        $material = Material::factory()->create([
            'quantity' => 1.0, // Only 1kg available
            'conversion_rate' => 1.0
        ]);

        // Recipe requires 5kg but only 1kg available
        $recipe->recipeMaterials()->attach($material->id, ['material_quantity' => 5.0]);

        $response = $this->getJson('/api/recipes/unpreparable');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unpreparable_recipes' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'insufficient_materials'
                        ]
                    ],
                    'count'
                ],
                'message'
            ]);

        $this->assertEquals(1, $response->json('data.count'));
    }

    /** @test */
    public function it_validates_calculate_cost_request()
    {
        $recipe = Recipe::factory()->create();

        $response = $this->postJson("/api/recipes/{$recipe->id}/calculate-cost", [
            'method' => 'invalid_method'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['method']);
    }
}

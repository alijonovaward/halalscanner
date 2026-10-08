<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'canonical_name' => Str::title($name),
            'slug' => Str::slug($name),
            'origin' => 'unknown',
            'requires_origin_evidence' => false,
            'requires_process_evidence' => false,
        ];
    }
}

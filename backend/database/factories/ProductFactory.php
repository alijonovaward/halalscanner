<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'manufacturer' => fake()->company(),
            'country_code' => fake()->countryCode(),
            'ingredients_text' => fake()->sentence(),
        ];
    }
}

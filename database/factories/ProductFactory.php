<?php

namespace Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(2, true)),
            'price' => fake()->numberBetween(100, 3000) * 100,
            'stock' => fake()->numberBetween(6, 60),
            'status' => ProductStatus::Draft,
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Live]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $nameEn = fake()->words(3, true);

        return [
            'category_id'         => Category::factory(),
            'name_ar'             => fake()->words(3, true),
            'name_en'             => $nameEn,
            'slug'                => Str::slug($nameEn) . '-' . fake()->unique()->numberBetween(1, 99999),
            'description_ar'      => fake()->paragraph(),
            'description_en'      => fake()->paragraph(),
            'cover_image'         => null,
            'price'               => fake()->randomFloat(2, 50, 5000),
            'discount_percentage' => fake()->numberBetween(0, 30),
            'quantity'            => fake()->numberBetween(1, 50),
            'metadata'            => ['weight' => '2 carat', 'origin' => 'Myanmar'],
            'is_active'           => true,
        ];
    }
}

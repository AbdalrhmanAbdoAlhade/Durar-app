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
        $name = fake()->unique()->words(2, true);

        return [
            'category_id' => Category::factory(),
            'name_ar' => $name,
            'name_en' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'description_ar' => fake()->sentence(),
            'description_en' => fake()->sentence(),
            'cover_image' => 'products/test.jpg',
            'price' => 1000,
            'discount_percentage' => 0,
            'quantity' => 10,
            'metadata' => ['weight' => '5g'],
            'is_active' => true,
        ];
    }
}

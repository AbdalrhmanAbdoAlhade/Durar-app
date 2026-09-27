<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $nameEn = fake()->words(2, true);

        return [
            'name_ar'    => fake()->words(2, true),
            'name_en'    => $nameEn,
            'slug'       => Str::slug($nameEn) . '-' . fake()->unique()->numberBetween(1, 99999),
            'image'      => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active'  => true,
        ];
    }
}

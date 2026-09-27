<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'                => strtoupper(fake()->bothify('??##??')),
            'type'                => fake()->randomElement(['percentage', 'fixed']),
            'value'               => fake()->numberBetween(5, 50),
            'max_discount_amount' => fake()->optional()->numberBetween(50, 500),
            'usage_limit'         => fake()->optional()->numberBetween(10, 1000),
            'used_count'          => 0,
            'starts_at'           => now()->subDay(),
            'expires_at'          => now()->addMonths(3),
            'is_active'           => true,
        ];
    }
}

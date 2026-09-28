<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('??####')),
            'user_id' => User::factory(),
            'type' => 'product',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total' => 1000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'shipping_address' => [
                'name' => fake()->name(),
                'phone' => fake()->numerify('01#########'),
                'city' => 'Cairo',
                'address_line' => fake()->streetAddress(),
            ],
        ];
    }
}

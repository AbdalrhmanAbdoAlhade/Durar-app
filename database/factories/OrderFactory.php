<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);

        return [
            'order_number'     => 'ORD-' . strtoupper(Str::random(10)),
            'user_id'          => User::factory(),
            'type'             => 'product',
            'auction_id'       => null,
            'coupon_id'        => null,
            'subtotal'         => $subtotal,
            'discount_amount'  => 0,
            'shipping_fee'     => 0,
            'total'            => $subtotal,
            'status'           => 'pending',
            'payment_status'   => 'unpaid',
            'shipping_address' => [
                'name'    => fake()->name(),
                'phone'   => '05' . fake()->numerify('########'),
                'address' => fake()->address(),
                'city'    => fake()->city(),
            ],
            'notes'            => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'         => 'processing',
            'payment_status' => 'paid',
        ]);
    }

    public function auction(): static
    {
        return $this->state(fn () => ['type' => 'auction']);
    }
}

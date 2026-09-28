<?php

namespace Database\Factories;

use App\Models\Auction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name_ar' => $name,
            'name_en' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'description_ar' => fake()->sentence(),
            'description_en' => fake()->sentence(),
            'cover_image' => 'auctions/test.jpg',
            'starting_price' => 1000,
            'current_price' => 1000,
            'min_bid_increment' => 50,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(3),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subMinute(),
        ]);
    }
}

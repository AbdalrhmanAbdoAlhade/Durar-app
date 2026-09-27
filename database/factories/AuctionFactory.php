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
        $nameEn = fake()->words(3, true);
        $price  = fake()->randomFloat(2, 100, 2000);

        return [
            'name_ar'           => fake()->words(3, true),
            'name_en'           => $nameEn,
            'slug'              => Str::slug($nameEn) . '-' . fake()->unique()->numberBetween(1, 99999),
            'description_ar'    => fake()->paragraph(),
            'description_en'    => fake()->paragraph(),
            'cover_image'       => null,
            'metadata'          => null,
            'starting_price'    => $price,
            'current_price'     => $price,
            'min_bid_increment' => 50,
            'starts_at'         => now()->subHour(),
            'ends_at'           => now()->addDays(3),
            'status'            => 'active',
            'winner_id'         => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status'    => 'scheduled',
            'starts_at' => now()->addDay(),
            'ends_at'   => now()->addDays(4),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status'    => 'ended',
            'starts_at' => now()->subDays(5),
            'ends_at'   => now()->subDay(),
        ]);
    }
}

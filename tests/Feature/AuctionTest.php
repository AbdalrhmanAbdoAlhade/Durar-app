<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_public_can_list_active_auctions(): void
    {
        Auction::factory()->create(['status' => 'active']);
        Auction::factory()->scheduled()->create();

        $response = $this->getJson('/api/auctions');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_public_can_show_auction(): void
    {
        $auction = Auction::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/auctions/{$auction->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $auction->id);
    }

    public function test_user_can_place_bid(): void
    {
        $auction = Auction::factory()->create([
            'status'            => 'active',
            'starting_price'    => 1000,
            'current_price'     => 1000,
            'min_bid_increment' => 50,
            'starts_at'         => now()->subHour(),
            'ends_at'           => now()->addDay(),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 1050,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 1050);

        $this->assertDatabaseHas('auction_bids', [
            'auction_id' => $auction->id,
            'user_id'    => $user->id,
            'amount'     => 1050,
        ]);

        $this->assertDatabaseHas('auctions', [
            'id'            => $auction->id,
            'current_price' => 1050,
        ]);
    }

    public function test_bid_below_minimum_fails(): void
    {
        $auction = Auction::factory()->create([
            'status'            => 'active',
            'starting_price'    => 1000,
            'current_price'     => 1000,
            'min_bid_increment' => 50,
            'starts_at'         => now()->subHour(),
            'ends_at'           => now()->addDay(),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 1020,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_bid_on_ended_auction(): void
    {
        $auction = Auction::factory()->ended()->create();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/auctions/{$auction->id}/bids", [
            'amount' => 2000,
        ]);

        $response->assertStatus(422);
    }

    public function test_list_bids(): void
    {
        $auction = Auction::factory()->create(['status' => 'active']);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        AuctionBid::create(['auction_id' => $auction->id, 'user_id' => $u1->id, 'amount' => 1100]);
        AuctionBid::create(['auction_id' => $auction->id, 'user_id' => $u2->id, 'amount' => 1200]);
        AuctionBid::create(['auction_id' => $auction->id, 'user_id' => $u3->id, 'amount' => 1300]);

        $response = $this->getJson("/api/auctions/{$auction->id}/bids");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_admin_can_close_auction(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $auction = Auction::factory()->create([
            'status'         => 'active',
            'starting_price' => 1000,
            'current_price'  => 1500,
            'starts_at'      => now()->subDay(),
            'ends_at'        => now()->addHour(),
        ]);

        $bidder = User::factory()->create();
        AuctionBid::create([
            'auction_id' => $auction->id,
            'user_id'    => $bidder->id,
            'amount'     => 1500,
        ]);

        $response = $this->postJson("/api/admin/auctions/{$auction->id}/close");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.winner_id', $bidder->id);
    }
}

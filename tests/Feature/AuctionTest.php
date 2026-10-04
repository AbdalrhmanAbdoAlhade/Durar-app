<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AuctionTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Push notifications are irrelevant here; avoid touching Firebase.
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    protected function address(): array
    {
        return ['name' => 'A', 'phone' => '010', 'city' => 'Cairo', 'address_line' => 'x'];
    }

    public function test_public_can_list_auctions(): void
    {
        Auction::factory()->create(['status' => 'active']);

        $this->getJson('/api/auctions')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_public_can_show_auction(): void
    {
        $auction = Auction::factory()->create(['status' => 'active']);

        $this->getJson("/api/auctions/{$auction->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $auction->id);
    }

    public function test_public_can_list_bids(): void
    {
        $auction = Auction::factory()->create();
        $user = User::factory()->create();
        $auction->bids()->create(['user_id' => $user->id, 'amount' => 1100]);

        $response = $this->getJson("/api/auctions/{$auction->id}/bids")->assertOk();
        $this->assertCount(1, $this->items($response));
    }

    public function test_guest_cannot_place_bid(): void
    {
        $auction = Auction::factory()->create();

        $this->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 2000])
            ->assertStatus(401);
    }

    public function test_user_can_place_valid_bid_and_price_updates(): void
    {
        $auction = Auction::factory()->create(['current_price' => 1000, 'min_bid_increment' => 50]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1050])
            ->assertStatus(201);

        $this->assertEquals(1050, (float) $auction->fresh()->current_price);
        $this->assertDatabaseHas('auction_bids', ['auction_id' => $auction->id, 'user_id' => $user->id]);
    }

    public function test_bid_validates_amount(): void
    {
        $auction = Auction::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", [])
            ->assertStatus(422);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 0])
            ->assertStatus(422);
    }

    public function test_bid_below_minimum_increment_is_rejected(): void
    {
        $auction = Auction::factory()->create(['current_price' => 1000, 'min_bid_increment' => 50]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1020])
            ->assertStatus(422);

        $this->assertSame(0, $auction->bids()->count());
    }

    public function test_cannot_bid_on_scheduled_or_ended_auction(): void
    {
        $scheduled = Auction::factory()->scheduled()->create();
        $ended = Auction::factory()->create(['status' => 'ended']);
        $user = User::factory()->create();

        foreach ([$scheduled, $ended] as $auction) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 5000])
                ->assertStatus(422);
        }
    }

    public function test_outbid_user_is_notified(): void
    {
        $auction = Auction::factory()->create(['current_price' => 1000, 'min_bid_increment' => 50]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $notifications = $this->mock(NotificationService::class);
        $notifications->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn ($user) => $user->id === $first->id);

        $this->actingAs($first, 'sanctum')->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1100])->assertStatus(201);
        $this->actingAs($second, 'sanctum')->postJson("/api/auctions/{$auction->id}/bids", ['amount' => 1200])->assertStatus(201);
    }

    public function test_process_command_activates_scheduled_and_closes_expired_auctions(): void
    {
        $dueToStart = Auction::factory()->scheduled()->create(['starts_at' => now()->subMinute()]);
        $expired = Auction::factory()->expired()->create(['current_price' => 1300]);
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $expired->bids()->create(['user_id' => $loser->id, 'amount' => 1100]);
        $expired->bids()->create(['user_id' => $winner->id, 'amount' => 1300]);

        Artisan::call('auctions:process');

        $this->assertSame('active', $dueToStart->fresh()->status);
        $this->assertSame('ended', $expired->fresh()->status);
        $this->assertSame($winner->id, $expired->fresh()->winner_id);
    }

    public function test_only_winner_can_checkout_an_ended_auction(): void
    {
        $winner = User::factory()->create();
        $other = User::factory()->create();
        $auction = Auction::factory()->create([
            'status' => 'ended',
            'winner_id' => $winner->id,
            'current_price' => 1300,
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/auctions/{$auction->id}/checkout", ['shipping_address' => $this->address()])
            ->assertStatus(422);
    }

    public function test_guest_cannot_checkout_auction(): void
    {
        $auction = Auction::factory()->create(['status' => 'ended']);

        $this->postJson("/api/auctions/{$auction->id}/checkout", ['shipping_address' => $this->address()])
            ->assertStatus(401);
    }

    public function test_guest_cannot_access_admin_auctions(): void
    {
        $this->getJson('/api/admin/auctions')->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_auctions(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/auctions')
            ->assertStatus(403);
    }

    public function test_admin_can_create_auction(): void
    {
        Storage::fake('public');

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->post('/api/admin/auctions', [
                'name_ar' => 'مزاد ذهب',
                'name_en' => 'Gold Auction',
                'starting_price' => 1000,
                'min_bid_increment' => 50,
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addDays(3)->toDateTimeString(),
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);
    }

    public function test_admin_create_auction_validates_required_fields(): void
    {
        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson('/api/admin/auctions', [])
            ->assertStatus(422);
    }

    public function test_admin_can_close_auction(): void
    {
        $auction = Auction::factory()->expired()->create();
        $winner = User::factory()->create();
        $auction->bids()->create(['user_id' => $winner->id, 'amount' => 1200]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson("/api/admin/auctions/{$auction->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertSame($winner->id, $auction->fresh()->winner_id);
    }

    public function test_admin_can_delete_auction(): void
    {
        $auction = Auction::factory()->create();

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->deleteJson("/api/admin/auctions/{$auction->id}")
            ->assertOk();

        $this->assertDatabaseMissing('auctions', ['id' => $auction->id]);
    }
}

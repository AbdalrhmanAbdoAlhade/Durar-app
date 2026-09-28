<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase, CreatesAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(NotificationService::class)->shouldIgnoreMissing();
    }

    protected function items($response): array
    {
        return $response->json('data.data') ?? $response->json('data');
    }

    public function test_new_review_is_pending_and_hidden_from_public(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 5, 'comment' => 'great'])
            ->assertStatus(201);

        $this->assertDatabaseHas('reviews', ['product_id' => $product->id, 'is_approved' => false]);

        $response = $this->getJson("/api/products/{$product->id}/reviews")->assertOk();
        $this->assertCount(0, $this->items($response));
    }

    public function test_approved_review_is_visible_publicly(): void
    {
        $product = Product::factory()->create();
        Review::create([
            'product_id' => $product->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 5,
            'is_approved' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}/reviews")->assertOk();
        $this->assertCount(1, $this->items($response));
    }

    public function test_guest_cannot_submit_review(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->id}/reviews", ['rating' => 5])
            ->assertStatus(401);
    }

    public function test_resubmitting_updates_the_same_review_and_resets_approval(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 3, 'is_approved' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 1])
            ->assertStatus(201);

        $this->assertSame(1, Review::count());
        $this->assertDatabaseHas('reviews', ['rating' => 1, 'is_approved' => false]);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 6])
            ->assertStatus(422);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 0])
            ->assertStatus(422);
    }

    public function test_rating_is_required(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [])
            ->assertStatus(422);
    }

    public function test_admin_can_list_pending_reviews(): void
    {
        $product = Product::factory()->create();
        Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 2, 'is_approved' => false]);
        Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'is_approved' => true]);

        $response = $this->actingAs($this->createAdmin(), 'sanctum')
            ->getJson('/api/admin/reviews/pending')
            ->assertOk();

        $this->assertCount(1, $this->items($response));
    }

    public function test_guest_cannot_list_pending_reviews(): void
    {
        $this->getJson('/api/admin/reviews/pending')->assertStatus(401);
    }

    public function test_admin_can_approve_review(): void
    {
        $review = Review::create([
            'product_id' => Product::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
        ]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")
            ->assertOk();

        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_regular_user_cannot_approve_review(): void
    {
        $review = Review::create([
            'product_id' => Product::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 4,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/reviews/{$review->id}/approve")
            ->assertStatus(403);
    }

    public function test_admin_can_delete_review(): void
    {
        $review = Review::create([
            'product_id' => Product::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 3,
        ]);

        $this->actingAs($this->createAdmin(), 'sanctum')
            ->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_regular_user_cannot_delete_review(): void
    {
        $review = Review::create([
            'product_id' => Product::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 3,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertStatus(403);
    }
}

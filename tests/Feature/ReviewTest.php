<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->product = Product::factory()->create(['is_active' => true]);
    }

    public function test_public_sees_only_approved_reviews(): void
    {
        Review::factory()->create([
            'product_id'  => $this->product->id,
            'is_approved' => true,
        ]);
        Review::factory()->create([
            'product_id'  => $this->product->id,
            'is_approved' => false,
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}/reviews");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_user_can_create_review(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
            'rating'  => 5,
            'comment' => 'Excellent product!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_approved', false);

        $this->assertDatabaseHas('reviews', [
            'user_id'    => $user->id,
            'product_id' => $this->product->id,
            'rating'     => 5,
        ]);
    }

    public function test_user_cannot_review_same_product_twice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Review::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->postJson("/api/products/{$this->product->id}/reviews", [
            'rating' => 4,
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_approve_review(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $review = Review::factory()->create([
            'product_id'  => $this->product->id,
            'is_approved' => false,
        ]);

        $response = $this->postJson("/api/admin/reviews/{$review->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.is_approved', true);
    }

    public function test_admin_can_delete_review(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $review = Review::factory()->create([
            'product_id'  => $this->product->id,
            'is_approved' => true,
        ]);

        $response = $this->deleteJson("/api/admin/reviews/{$review->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}

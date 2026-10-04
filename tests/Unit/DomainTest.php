<?php

namespace Tests\Unit;

use App\Models\Auction;
use App\Models\Coupon;
use App\Models\Product;
use Tests\TestCase;

class DomainTest extends TestCase
{
    public function test_coupon_percentage_discount(): void
    {
        $coupon = new Coupon(['type' => 'percentage', 'value' => 10]);

        $this->assertSame(20.0, $coupon->calculateDiscount(200));
    }

    public function test_coupon_fixed_discount(): void
    {
        $coupon = new Coupon(['type' => 'fixed', 'value' => 50]);

        $this->assertSame(50.0, $coupon->calculateDiscount(200));
    }

    public function test_coupon_discount_never_exceeds_amount(): void
    {
        $coupon = new Coupon(['type' => 'fixed', 'value' => 500]);

        $this->assertSame(200.0, $coupon->calculateDiscount(200));
    }

    public function test_coupon_respects_max_discount_amount(): void
    {
        $coupon = new Coupon(['type' => 'percentage', 'value' => 50, 'max_discount_amount' => 30]);

        $this->assertSame(30.0, $coupon->calculateDiscount(200));
    }

    public function test_product_final_price_without_discount(): void
    {
        $product = new Product(['price' => 1000, 'discount_percentage' => 0]);

        $this->assertSame(1000.0, $product->final_price);
    }

    public function test_product_final_price_with_discount(): void
    {
        $product = new Product(['price' => 1000, 'discount_percentage' => 20]);

        $this->assertSame(800.0, $product->final_price);
    }

    public function test_auction_minimum_next_bid(): void
    {
        $auction = new Auction(['current_price' => 1000, 'min_bid_increment' => 50]);

        $this->assertSame(1050.0, $auction->minimumNextBid());
    }
}

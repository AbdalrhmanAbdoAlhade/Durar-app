<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(protected CouponService $coupons)
    {
    }

    /**
     * Checkout the given cart into an order. Validates stock, applies an optional
     * category-scoped coupon, decrements stock, and opens a pending payment record.
     */
    public function checkoutCart(Cart $cart, int $userId, array $shippingAddress, ?string $couponCode = null, float $shippingFee = 0): Order
    {
        $cart->loadMissing('items.product.category');

        if ($cart->items->isEmpty()) {
            abort(422, 'Cart is empty');
        }

        return DB::transaction(function () use ($cart, $userId, $shippingAddress, $couponCode, $shippingFee) {
            foreach ($cart->items as $item) {
                $product = $item->product()->lockForUpdate()->first();
                if ($product->quantity < $item->quantity) {
                    abort(422, "Insufficient stock for {$product->name_en}");
                }
            }

            $subtotal = round($cart->items->sum(fn ($i) => $i->unit_price * $i->quantity), 2);

            $coupon = null;
            $discount = 0;

            if ($couponCode) {
                $categoryIds = $cart->items->pluck('product.category_id')->unique()->values()->all();
                $coupon = $this->coupons->validateForCategories($couponCode, $categoryIds);

                if (! $coupon) {
                    abort(422, 'Coupon is invalid or does not apply to items in your cart');
                }

                $qualifyingSubtotal = round(
                    $cart->items
                        ->filter(fn ($i) => in_array($i->product->category_id, $coupon->categories->pluck('id')->all()))
                        ->sum(fn ($i) => $i->unit_price * $i->quantity),
                    2
                );

                $discount = $coupon->calculateDiscount($qualifyingSubtotal);
            }

            $total = round($subtotal - $discount + $shippingFee, 2);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'type' => 'product',
                'coupon_id' => $coupon?->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => round($item->unit_price * $item->quantity, 2),
                ]);

                $item->product()->decrement('quantity', $item->quantity);
            }

            if ($coupon) {
                $coupon->increment('used_count');
            }

            $this->openPendingPayment($order);

            $cart->items()->delete();

            return $order->load('items.product', 'coupon');
        });
    }

    /**
     * Checkout an auction win into a single-item (auction-type) order.
     */
    public function checkoutAuctionWin(Auction $auction, int $userId, array $shippingAddress): Order
    {
        if ($auction->status !== 'ended' || $auction->winner_id !== $userId) {
            abort(422, 'You are not the winner of this auction, or it has not ended yet');
        }

        if (Order::where('auction_id', $auction->id)->exists()) {
            abort(422, 'An order for this auction already exists');
        }

        return DB::transaction(function () use ($auction, $userId, $shippingAddress) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'type' => 'auction',
                'auction_id' => $auction->id,
                'subtotal' => $auction->current_price,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'total' => $auction->current_price,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address' => $shippingAddress,
            ]);

            $this->openPendingPayment($order);

            return $order->load('auction');
        });
    }

    protected function openPendingPayment(Order $order): Payment
    {
        return $order->payments()->create([
            'gateway' => 'edfapay',
            'reference' => $order->order_number,
            'amount' => $order->total,
            'currency' => 'SAR',
            'status' => 'pending',
        ]);
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}

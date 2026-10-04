<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartService
{
    public function getOrCreateCart(Request $request): Cart
    {
        // مستخدم مسجل دخول
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        // زائر (ويب أو موبايل)
        $guestId = $this->resolveGuestId($request);

        return Cart::firstOrCreate([
            'session_id' => $guestId,
            'user_id'    => null,
        ]);
    }

    /**
     * يجيب معرف الزائر من:
     * 1. Header: X-Device-Id أو X-Cart-Token
     * 2. Body: session_id أو device_id
     * 3. Laravel session (للويب فقط)
     */
    public function resolveGuestId(Request $request): string
{
    $guestId = $request->header('X-Device-Id')
            ?? $request->header('X-Cart-Token')
            ?? $request->input('session_id')
            ?? $request->input('device_id');

    if ($guestId) {
        return (string) $guestId;
    }

    // fallback للويب
    return $request->session()->getId();
}

    public function addItem(Cart $cart, int $productId, int $quantity): Cart
    {
        $product = Product::findOrFail($productId);

        if ($product->quantity < $quantity) {
            abort(422, 'Requested quantity exceeds available stock');
        }

        $item = $cart->items()->where('product_id', $productId)->first();

        if ($item) {
            $newQuantity = $item->quantity + $quantity;

            if ($product->quantity < $newQuantity) {
                abort(422, 'Requested quantity exceeds available stock');
            }

            $item->update([
                'quantity'   => $newQuantity,
                'unit_price' => $product->final_price,
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity'   => $quantity,
                'unit_price' => $product->final_price,
            ]);
        }

        return $cart->fresh(['items.product']);
    }

    public function updateItemQuantity(Cart $cart, int $itemId, int $quantity): Cart
    {
        $item = $cart->items()->findOrFail($itemId);

        if ($quantity === 0) {
            $item->delete();
            return $cart->fresh(['items.product']);
        }

        if ($item->product->quantity < $quantity) {
            abort(422, 'Requested quantity exceeds available stock');
        }

        $item->update(['quantity' => $quantity]);

        return $cart->fresh(['items.product']);
    }

    public function removeItem(Cart $cart, int $itemId): Cart
    {
        $cart->items()->where('id', $itemId)->delete();
        return $cart->fresh(['items.product']);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function mergeGuestCartIntoUser(string $guestId, int $userId): void
    {
        $guestCart = Cart::where('session_id', $guestId)
                         ->whereNull('user_id')
                         ->first();

        if (! $guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $userId]);

        foreach ($guestCart->items as $item) {
            $this->addItem($userCart, $item->product_id, $item->quantity);
        }

        $guestCart->delete();
    }
}
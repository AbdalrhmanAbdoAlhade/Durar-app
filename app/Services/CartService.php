<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class CartService
{
    public function getOrCreateCart(Request $request): Cart
    {
        if ($request->user()) {
            return Cart::firstOrCreate(['user_id' => $request->user()->id]);
        }

        $sessionId = $request->session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId, 'user_id' => null]);
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
            $item->update(['quantity' => $newQuantity, 'unit_price' => $product->final_price]);
        } else {
            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $product->final_price,
            ]);
        }

        return $cart->fresh(['items.product']);
    }

    public function updateItemQuantity(Cart $cart, int $itemId, int $quantity): Cart
    {
        $item = $cart->items()->findOrFail($itemId);

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

    /**
     * Merge a guest session cart into the authenticated user's cart (e.g. right after login).
     */
    public function mergeGuestCartIntoUser(string $sessionId, int $userId): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->first();
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

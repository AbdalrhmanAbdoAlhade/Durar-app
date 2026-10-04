<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected CartService $carts)
    {
    }

    // GET /api/cart
    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request)->load('items.product');

        return $this->success(new CartResource($cart));
    }

    // POST /api/cart/items
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request);
        $cart = $this->carts->addItem($cart, $request->integer('product_id'), $request->integer('quantity'));

        return $this->success(new CartResource($cart), 'Item added to cart');
    }

    // PUT /api/cart/items/{item}
    public function updateItem(UpdateCartItemRequest $request, int $item): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request);
        $cart = $this->carts->updateItemQuantity($cart, $item, $request->integer('quantity'));

        return $this->success(new CartResource($cart), 'Cart item updated');
    }

    // DELETE /api/cart/items/{item}
    public function removeItem(Request $request, int $item): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request);
        $cart = $this->carts->removeItem($cart, $item);

        return $this->success(new CartResource($cart), 'Item removed from cart');
    }

    // DELETE /api/cart
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request);
        $this->carts->clear($cart);

        return $this->success(null, 'Cart cleared');
    }
}

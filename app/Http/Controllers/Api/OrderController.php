<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuctionCheckoutRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Auction;
use App\Models\Order;
use App\Services\CartService;
use App\Services\EdfaPayService;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected OrderService $orders,
        protected CartService $carts,
        protected EdfaPayService $edfapay,
        protected \App\Services\NotificationService $notifications,
    ) {
    }

    // GET /api/orders (auth)
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->paginate(20);

        return $this->success(
            OrderResource::collection($orders)
        );
    }

    // GET /api/orders/{order} (auth, owner only)
    public function show(
        Request $request,
        Order $order
    ): JsonResponse {
        if ($order->user_id !== $request->user()->id) {
            return $this->error('Not found', 404);
        }

        return $this->success(
            new OrderResource(
                $order->load(
                    'items.product',
                    'coupon',
                    'auction'
                )
            )
        );
    }

    // POST /api/orders/checkout (auth)
    public function checkout(
        CheckoutRequest $request
    ): JsonResponse {
        $cart = $this->carts->getOrCreateCart($request);

        $order = $this->orders->checkoutCart(
            $cart,
            $request->user()->id,
            $request->input('shipping_address'),
            $request->input('coupon_code'),
            (float) $request->input('shipping_fee', 0)
        );

        $redirectUrl = $this->edfapay->initiate($order, [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'phone' => $request->user()->phone,
        ]);

        // WhatsApp - Order Created
        WhatsAppService::orderCreated($order);

        return $this->success([
            'order' => new OrderResource($order),
            'payment_redirect_url' => $redirectUrl,
        ], 'Order created, proceed to payment', 201);
    }

    // POST /api/auctions/{auction}/checkout (auth)
    public function checkoutAuction(
        AuctionCheckoutRequest $request,
        Auction $auction
    ): JsonResponse {
        $order = $this->orders->checkoutAuctionWin(
            $auction,
            $request->user()->id,
            $request->input('shipping_address')
        );

        $redirectUrl = $this->edfapay->initiate($order, [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
            'phone' => $request->user()->phone,
        ]);

        // WhatsApp - Order Created
        WhatsAppService::orderCreated($order);

        return $this->success([
            'order' => new OrderResource($order),
            'payment_redirect_url' => $redirectUrl,
        ], 'Order created, proceed to payment', 201);
    }

    // GET /api/admin/orders (admin)
    public function adminIndex(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('items.product', 'user')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->status(
                    $request->string('status')
                )
            )
            ->latest()
            ->paginate(20);

        return $this->success(
            OrderResource::collection($orders)
        );
    }

    // PUT /api/admin/orders/{order}/status (admin)
    public function updateStatus(
        Request $request,
        Order $order
    ): JsonResponse {
        $request->validate([
            'status' => [
                'required',
                'in:pending,processing,shipped,delivered,cancelled'
            ],
        ]);

        $newStatus = $request->string('status')->value();

        $order->update([
            'status' => $newStatus,
        ]);

        // =====================================================
        // In-App Notification
        // =====================================================

        $this->notifications->sendToUser(
            $order->user,
            'تحديث حالة طلبك',
            "طلبك رقم {$order->order_number} بقى: {$order->status}"
        );

        // =====================================================
        // WhatsApp Notifications
        // =====================================================

        // 1. Shipped
        if ($newStatus === 'shipped') {
            WhatsAppService::orderShipped($order);
        }

        // 2. Cancelled
        elseif ($newStatus === 'cancelled') {
            WhatsAppService::orderCancelled($order);
        }

        // 3. Delivered -> Request Review
        elseif ($newStatus === 'delivered') {
            $reviewUrl = config('app.frontend_url')
                . '/orders/'
                . $order->id
                . '/review';

            WhatsAppService::reviewRequest(
                $order,
                $reviewUrl
            );
        }

        return $this->success(
            new OrderResource($order),
            'Order status updated'
        );
    }
}
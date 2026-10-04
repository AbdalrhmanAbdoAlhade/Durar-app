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
use Illuminate\Support\Facades\DB;

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

    // GET /api/orders
    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.product')
            ->latest()
            ->paginate(20);

        return $this->success(OrderResource::collection($orders));
    }

    // GET /api/orders/{order}
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error('Not found', 404);
        }

        return $this->success(
            new OrderResource($order->load('items.product', 'coupon', 'auction'))
        );
    }

    // POST /api/orders/checkout
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $cart = $this->carts->getOrCreateCart($request);

        // مفيش shipping_fee من الكلاينت — بيتحسب في OrderService
      $order = $this->orders->checkoutCart(
    $cart,
    $request->user()->id,
    $request->input('shipping_address'),
    $request->input('coupon_code'),
    $request->filled('shipping_fee')
        ? (float) $request->input('shipping_fee')
        : null   // null → السيرفر يحط 0
);

        $redirectUrl = $this->edfapay->initiate($order, [
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
            'phone' => $request->user()->phone,
        ]);

        WhatsAppService::orderCreated($order);

        return $this->success([
            'order'                => new OrderResource($order),
            'payment_redirect_url' => $redirectUrl,
        ], 'Order created, proceed to payment', 201);
    }

    // POST /api/auctions/{auction}/checkout
    public function checkoutAuction(AuctionCheckoutRequest $request, Auction $auction): JsonResponse
    {
        $order = $this->orders->checkoutAuctionWin(
            $auction,
            $request->user()->id,
            $request->input('shipping_address')
        );

        $redirectUrl = $this->edfapay->initiate($order, [
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
            'phone' => $request->user()->phone,
        ]);

        WhatsAppService::orderCreated($order);

        return $this->success([
            'order'                => new OrderResource($order),
            'payment_redirect_url' => $redirectUrl,
        ], 'Order created, proceed to payment', 201);
    }

    // POST /api/orders/{order}/retry-payment
    public function retryPayment(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error('Not found', 404);
        }

        if (! in_array($order->payment_status, ['unpaid', 'failed'], true)) {
            return $this->error('Payment cannot be retried for this order', 422);
        }

        // جدد الحجوزات المنتهية/المحررة
        $order->reservations()
            ->whereIn('status', ['released', 'reserved'])
            ->update([
                'status'     => 'reserved',
                'expires_at' => now()->addMinutes(15),
            ]);

        // تأكد إن المخزون لسه كافي
        foreach ($order->items as $item) {
            $product = $item->product()->lockForUpdate()->first();

            $reserved = $product->reservations()
                ->where('status', 'reserved')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            // استثني حجوزات نفس الطلب من الحساب
            $otherReserved = $product->reservations()
                ->where('status', 'reserved')
                ->where('expires_at', '>', now())
                ->where('order_id', '!=', $order->id)
                ->sum('quantity');

            $available = max(0, $product->quantity - $otherReserved);

            if ($available < $item->quantity) {
                return $this->error("Insufficient stock for {$product->name_en}", 422);
            }
        }

        $order->payments()->create([
            'gateway'   => 'edfapay',
            'reference' => $order->order_number,
            'amount'    => $order->total,
            'currency'  => 'SAR',
            'status'    => 'pending',
        ]);

        $order->update([
            'payment_status' => 'unpaid',
            'status'         => 'pending',
        ]);

        $redirectUrl = $this->edfapay->initiate($order, [
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
            'phone' => $request->user()->phone,
        ]);

        return $this->success([
            'order'                => new OrderResource($order->fresh(['items.product'])),
            'payment_redirect_url' => $redirectUrl,
        ], 'Retry payment initiated');
    }

    // GET /api/admin/orders
    public function adminIndex(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('items.product', 'user')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->status($request->string('status'))
            )
            ->latest()
            ->paginate(20);

        return $this->success(OrderResource::collection($orders));
    }

    // PUT /api/admin/orders/{order}/status
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);

        $newStatus = $request->string('status')->value();

        DB::transaction(function () use ($order, $newStatus) {
            $order->update(['status' => $newStatus]);

            // استرجاع المخزون عند الإلغاء
            if ($newStatus === 'cancelled') {
                $committed = $order->reservations()
                    ->where('status', 'committed')
                    ->lockForUpdate()
                    ->get();

                foreach ($committed as $res) {
                    $res->product()->increment('quantity', $res->quantity);
                    $res->update(['status' => 'released']);
                }

                $order->reservations()
                    ->where('status', 'reserved')
                    ->update(['status' => 'released']);
            }
        });

        $this->notifications->sendToUser(
            $order->user,
            'تحديث حالة طلبك',
            "طلبك رقم {$order->order_number} بقى: {$order->status}"
        );

        if ($newStatus === 'shipped') {
            WhatsAppService::orderShipped($order);
        } elseif ($newStatus === 'cancelled') {
            WhatsAppService::orderCancelled($order);
        } elseif ($newStatus === 'delivered') {
            $reviewUrl = config('app.frontend_url') . '/orders/' . $order->id . '/review';
            WhatsAppService::reviewRequest($order, $reviewUrl);
        }

        return $this->success(
            new OrderResource($order->fresh()),
            'Order status updated'
        );
    }
}
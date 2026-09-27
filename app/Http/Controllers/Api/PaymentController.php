<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\EdfaPayService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected EdfaPayService $edfapay,
        protected NotificationService $notifications,
    ) {
    }

    // GET /api/orders/{order}/payment (auth, owner only)
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return $this->error('Not found', 404);
        }

        return $this->success(new PaymentResource($order->latestPayment));
    }

    /**
     * POST /api/payments/edfapay/webhook
     *
     * We don't trust the callback payload's own fields for the final decision —
     * we re-verify the transaction directly against EdfaPay's /payment/status
     * endpoint using our own merchant credentials, then update our records from
     * that response. See EdfaPayService docblock re: confirming the hash formula.
     */
    public function edfapayWebhook(Request $request): JsonResponse
    {
        $orderNumber = $request->input('order_id');

        if (! $orderNumber) {
            return $this->error('Missing order_id', 422);
        }

        $order = Order::where('order_number', $orderNumber)->with(['latestPayment', 'user'])->first();

        if (! $order || ! $order->latestPayment) {
            Log::warning('EdfaPay webhook for unknown order', ['order_id' => $orderNumber]);

            return $this->error('Order not found', 404);
        }

        $verified = $this->edfapay->checkStatus($orderNumber);

        if (! $verified) {
            Log::error('EdfaPay status re-verification failed', ['order_id' => $orderNumber]);

            return $this->error('Could not verify transaction status', 502);
        }

        $status = strtoupper((string) ($verified['status'] ?? $verified['action'] ?? ''));
        $isSuccess = in_array($status, ['SUCCESS', 'APPROVED', 'CAPTURED'], true);
        $isFailed = in_array($status, ['DECLINED', 'FAILED', 'ERROR'], true);

        $order->latestPayment->update([
            'transaction_id' => $verified['trans_id'] ?? $verified['transactionId'] ?? $order->latestPayment->transaction_id,
            'status' => $isSuccess ? 'success' : ($isFailed ? 'failed' : $order->latestPayment->status),
            'gateway_response' => $verified,
        ]);

        if ($isSuccess) {
            $order->update(['payment_status' => 'paid', 'status' => 'processing']);
            $this->notifications->sendToUser($order->user, 'تم الدفع بنجاح', "طلبك رقم {$order->order_number} اتدفع بنجاح.");
        } elseif ($isFailed) {
            $order->update(['payment_status' => 'failed']);
            $this->notifications->sendToUser($order->user, 'فشلت عملية الدفع', "الدفع لطلبك رقم {$order->order_number} متنفذش. جرب تاني.");
        }

        return $this->success(null, 'Webhook processed');
    }
}

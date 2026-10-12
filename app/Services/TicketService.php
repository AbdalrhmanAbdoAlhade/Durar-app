<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketBooking;
use App\Models\TicketBookingItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    public function __construct(
        protected EdfaPayService $edfapay
    ) {}
	
  	/**
 * إلغاء حجز (بشرط باقي أكثر من 7 أيام على الموعد)
 */
public function cancelBooking(TicketBooking $booking): TicketBooking
{
    if (in_array($booking->status, ['cancelled', 'used'])) {
        abort(422, 'لا يمكن إلغاء هذا الحجز');
    }

    // نجيب أقرب موعد حدث في عناصر الحجز
    $earliestStart = $booking->items
        ->map(fn ($item) => $item->ticket?->event_start)
        ->filter()
        ->min();

    if (!$earliestStart) {
        abort(422, 'لا يمكن تحديد موعد الحدث');
    }

    // لازم يكون باقي أكثر من 7 أيام
    if ($earliestStart->lte(now()->addDays(7))) {
        abort(422, 'لا يمكن الإلغاء قبل الموعد بأقل من أسبوع');
    }

    $booking->update([
        'status' => 'cancelled',
    ]);

    // لو عايز ترجع الكمية للتذاكر:
    foreach ($booking->items as $item) {
        if ($item->ticket && ! $item->ticket->isUnlimited()) {
            $item->ticket->increment('quantity', $item->quantity);
        }
    }

    return $booking->fresh(['items.ticket', 'order']);
}
  
  	/**
       * كل الحجوزات للأدمن
       */
      public function getAllBookings(?string $paymentStatus = null, ?string $status = null, int $perPage = 15)
      {
          return TicketBooking::query()
              ->when($paymentStatus, fn ($q) => $q->where('payment_status', $paymentStatus))
              ->when($status, fn ($q) => $q->where('status', $status))
              ->with(['items.ticket', 'order:id,order_number,status,payment_status', 'user:id,name,email,phone'])
              ->latest()
              ->paginate($perPage);
      }
  	
    /**
     * توليد باركود فريد وغير قابل للتزوير
     */
    public function generateSecureBarcodeToken(int $bookingId, string $bookingCode): string
    {
        $payload = $bookingId . '|' . $bookingCode;

        $signature = hash_hmac('sha256', $payload, config('app.ticket_secret'));

        $token = strtoupper(
            substr($signature, 0, 16) .
            str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT)
        );

        return $token;
    }

    /**
     * البحث عن حجز باستخدام الباركود
     */
    public function findByBarcodeToken(string $token): ?TicketBooking
    {
        return TicketBooking::with(['items.ticket', 'order:id,order_number,status,payment_status'])
            ->where('barcode_token', $token)
            ->first();
    }

    /**
     * حجوزات مستخدم مسجّل
     */
    public function getUserBookings(int $userId, ?string $paymentStatus = null, int $perPage = 15)
    {
        return TicketBooking::query()
            ->where('user_id', $userId)
            ->when($paymentStatus, fn ($q) => $q->where('payment_status', $paymentStatus))
            ->with(['items.ticket', 'order:id,order_number,status,payment_status'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * حجز واحد يخص المستخدم
     */
    public function getUserBooking(int $userId, int $bookingId): TicketBooking
    {
        return TicketBooking::query()
            ->where('user_id', $userId)
            ->with(['items.ticket', 'order:id,order_number,status,payment_status'])
            ->findOrFail($bookingId);
    }

    /**
     * بحث الـ Guest: كود الحجز + رقم التليفون
     */
    public function findGuestBooking(string $bookingCode, string $phone): TicketBooking
    {
        return TicketBooking::query()
            ->where('booking_code', $bookingCode)
            ->where('phone', $phone)
            ->with(['items.ticket', 'order:id,order_number,status,payment_status'])
            ->firstOrFail();
    }

    /**
     * حجز تذاكر (Guest أو User)
     */
    public function bookTickets(array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $userId) {

            $itemsInput = $data['items'];
            $ticketIds  = collect($itemsInput)->pluck('ticket_id')->unique()->all();

            // قفل الصفوف لمنع Overbooking
            $tickets = Ticket::whereIn('id', $ticketIds)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tickets->count() !== count($ticketIds)) {
                abort(422, 'One or more tickets are invalid or inactive');
            }

            $subtotal      = 0;
            $totalDiscount = 0;
            $preparedItems = [];

            foreach ($itemsInput as $row) {
                $ticket = $tickets[$row['ticket_id']];
                $qty    = (int) $row['quantity'];

                if ($qty < 1) {
                    abort(422, "Invalid quantity for ticket: {$ticket->name}");
                }

                if (! $ticket->hasAvailableQuantity($qty)) {
                    abort(422, "Insufficient quantity for ticket: {$ticket->name}");
                }

                $unitPrice    = (float) $ticket->price;
                $lineDiscount = (float) $ticket->discount_amount * $qty;
                $lineTotal    = max(0, ($unitPrice * $qty) - $lineDiscount);

                $subtotal      += $unitPrice * $qty;
                $totalDiscount += $lineDiscount;

                $preparedItems[] = [
                    'ticket'          => $ticket,
                    'quantity'        => $qty,
                    'unit_price'      => $unitPrice,
                    'discount_amount' => $lineDiscount,
                    'total'           => $lineTotal,
                ];
            }

            $total = round($subtotal - $totalDiscount, 2);

            // 1) إنشاء الحجز
            $booking = TicketBooking::create([
                'booking_code'    => $this->generateBookingCode(),
                'user_id'         => $userId,
                'customer_name'   => $data['customer_name'],
                'national_id'     => $data['national_id'],
                'phone'           => $data['phone'],
                'subtotal'        => round($subtotal, 2),
                'discount_amount' => round($totalDiscount, 2),
                'total'           => $total,
                'status'          => 'pending',
                'payment_status'  => 'unpaid',
            ]);

            // 2) توليد الباركود وحفظه
            $booking->barcode_token = $this->generateSecureBarcodeToken(
                $booking->id,
                $booking->booking_code
            );
            $booking->save();

            // 3) عناصر الحجز + خصم الكمية
            foreach ($preparedItems as $item) {
                TicketBookingItem::create([
                    'ticket_booking_id' => $booking->id,
                    'ticket_id'         => $item['ticket']->id,
                    'quantity'          => $item['quantity'],
                    'unit_price'        => $item['unit_price'],
                    'discount_amount'   => $item['discount_amount'],
                    'total'             => $item['total'],
                ]);

                if (! $item['ticket']->isUnlimited()) {
                    $item['ticket']->decrement('quantity', $item['quantity']);
                }
            }

            // 4) إنشاء Order
            $order = Order::create([
                'order_number'     => $this->generateOrderNumber(),
                'user_id'          => $userId,
                'type'             => 'ticket',
                'subtotal'         => $booking->subtotal,
                'discount_amount'  => $booking->discount_amount,
                'shipping_fee'     => 0,
                'total'            => $booking->total,
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'shipping_address' => null,
            ]);

            $booking->update(['order_id' => $order->id]);

            // 5) سجل الدفع
            $order->payments()->create([
                'gateway'   => 'edfapay',
                'reference' => $order->order_number,
                'amount'    => $order->total,
                'currency'  => 'SAR',
                'status'    => 'pending',
            ]);

            // 6) بدء جلسة الدفع
            $redirectUrl = $this->edfapay->initiate($order, [
                'name'  => $data['customer_name'],
                'phone' => $data['phone'],
            ]);

            return [
                'booking'      => $booking->load('items.ticket'),
                'order'        => $order,
                'redirect_url' => $redirectUrl,
            ];
        });
    }

    public function generateBookingCode(): string
    {
        do {
            $code = 'TKT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (TicketBooking::where('booking_code', $code)->exists());

        return $code;
    }

    protected function generateOrderNumber(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
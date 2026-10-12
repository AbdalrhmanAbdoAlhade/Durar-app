<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookTicketsRequest;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Milon\Barcode\DNS1D;

class TicketBookingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * GET /api/my-tickets
     */
    public function myBookings(Request $request): JsonResponse
    {
        $request->validate([
            'payment_status' => ['nullable', 'in:unpaid,paid,failed,refunded'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $bookings = $this->ticketService
            ->getUserBookings(
                $request->user()->id,
                $request->payment_status,
                (int) ($request->per_page ?? 15)
            )
            ->through(fn ($booking) => $this->formatBooking($booking));

        return $this->success($bookings);
    }

    /**
     * GET /api/my-tickets/{id}
     */
    public function showMyBooking(Request $request, int $id): JsonResponse
    {
        $booking = $this->ticketService->getUserBooking($request->user()->id, $id);
        return $this->success($this->formatBooking($booking));
    }

    /**
     * POST /api/tickets/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_code' => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:20'],
        ]);

        $booking = $this->ticketService->findGuestBooking(
            $data['booking_code'],
            $data['phone']
        );

        return $this->success($this->formatBooking($booking));
    }

    /**
     * POST /api/tickets/book
     */
    public function book(BookTicketsRequest $request): JsonResponse
    {
        $userId = $request->user('sanctum')?->id;

        $result = $this->ticketService->bookTickets(
            $request->validated(),
            $userId
        );

        $booking = $result['booking'];

        return $this->success([
            'booking_id'    => $booking->id,
            'booking_code'  => $booking->booking_code,
            'barcode_token' => $booking->barcode_token,
            'order_number'  => $result['order']->order_number,
            'total'         => $booking->total,
            'redirect_url'  => $result['redirect_url'],
            'items'         => $booking->items->map(function ($item) {
                $ticket = $item->ticket;
                $from   = $ticket?->event_start;
                $to     = $ticket?->event_end;

                return [
                    'ticket_id'   => $item->ticket_id,
                    'ticket_name' => $ticket?->name,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'discount'    => $item->discount_amount,
                    'total'       => $item->total,
                    'location'    => $ticket?->location,
                    'visit_date'  => $from?->toDateString(),
                    'from_time'   => $from?->format('H:i'),
                    'to_time'     => $to?->format('H:i'),
                    'time_period' => $from && $to
                        ? $from->format('h:i A') . ' - ' . $to->format('h:i A')
                        : null,
                ];
            }),
        ], 'Booking created successfully. Please complete payment.');
    }

    /**
     * GET /api/tickets/barcode/{token}
     */
    public function generateBarcode(string $token)
    {
        $booking = $this->ticketService->findByBarcodeToken($token);

        if (!$booking) {
            abort(404, 'الباركود غير موجود');
        }

        $barcode = new DNS1D();
        $image   = $barcode->getBarcodePNG($token, 'C128', 2.5, 80);

        return response(base64_decode($image))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }
  
  	/**
 * POST /api/my-tickets/{id}/cancel
 */
public function cancel(Request $request, int $id): JsonResponse
{
    $booking = $this->ticketService->getUserBooking($request->user()->id, $id);

    $cancelled = $this->ticketService->cancelBooking($booking);

    return $this->success(
        $this->formatBooking($cancelled),
        'تم إلغاء الحجز بنجاح'
    );
}

    /**
     * POST /api/tickets/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:40'],
        ]);

        $booking = $this->ticketService->findByBarcodeToken($data['barcode']);

        if (!$booking) {
            return $this->error('الباركود غير موجود', 404);
        }

        if ($booking->payment_status !== 'paid') {
            return $this->error('التذكرة غير مدفوعة', 402);
        }

        if (in_array($booking->status, ['cancelled', 'used'])) {
            return $this->error('التذكرة ملغاة أو مستخدمة مسبقاً', 410);
        }

        return $this->success([
            'valid'          => true,
            'booking_code'   => $booking->booking_code,
            'customer_name'  => $booking->customer_name,
            'phone'          => $booking->phone,
            'total'          => $booking->total,
            'payment_status' => $booking->payment_status,
            'items'          => $booking->items->map(fn ($item) => [
                'ticket_name' => $item->ticket?->name,
                'quantity'    => $item->quantity,
            ]),
            'message'        => 'التذكرة صالحة ✓',
        ]);
    }

    protected function formatBooking($booking): array
    {
        return [
            'id'             => $booking->id,
            'booking_code'   => $booking->booking_code,
            'barcode_token'  => $booking->barcode_token,
            'barcode_image'  => $booking->barcode_token
                ? url('/api/tickets/barcode/' . $booking->barcode_token)
                : null,
            'customer_name'  => $booking->customer_name,
            'phone'          => $booking->phone,
            'subtotal'       => $booking->subtotal,
            'discount'       => $booking->discount_amount,
            'total'          => $booking->total,
            'status'         => $booking->status,
            'payment_status' => $booking->payment_status,
            'order_number'   => $booking->order?->order_number,
            'created_at'     => $booking->created_at,

            'items' => $booking->items->map(function ($item) {
                $ticket = $item->ticket;
                $from   = $ticket?->event_start;
                $to     = $ticket?->event_end;

                return [
                    'ticket_id'   => $item->ticket_id,
                    'ticket_name' => $ticket?->name,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'discount'    => $item->discount_amount,
                    'total'       => $item->total,
                    'location'    => $ticket?->location,
                    'visit_date'  => $from?->toDateString(),
                    'from_time'   => $from?->format('H:i'),
                    'to_time'     => $to?->format('H:i'),
                    'time_period' => $from && $to
                        ? $from->format('h:i A') . ' - ' . $to->format('h:i A')
                        : null,
                ];
            }),
        ];
    }
}
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\TicketService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketBookingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * GET /api/admin/bookings
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'payment_status' => ['nullable', 'in:unpaid,paid,failed,refunded'],
            'status'         => ['nullable', 'in:pending,confirmed,cancelled,used'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $bookings = $this->ticketService->getAllBookings(
            $request->payment_status,
            $request->status,
            (int) ($request->per_page ?? 15)
        );

        $formatted = $bookings->through(function ($booking) {
            return [
                'id'             => $booking->id,
                'booking_code'   => $booking->booking_code,
                'barcode_token'  => $booking->barcode_token,
                'barcode_image'  => $booking->barcode_token
                    ? url('/api/tickets/barcode/' . $booking->barcode_token)
                    : null,
                'customer_name'  => $booking->customer_name,
                'phone'          => $booking->phone,
                'national_id'    => $booking->national_id,
                'subtotal'       => $booking->subtotal,
                'discount'       => $booking->discount_amount,
                'total'          => $booking->total,
                'status'         => $booking->status,
                'payment_status' => $booking->payment_status,
                'order_number'   => $booking->order?->order_number,
                'user'           => $booking->user ? [
                    'id'    => $booking->user->id,
                    'name'  => $booking->user->name,
                    'email' => $booking->user->email,
                    'phone' => $booking->user->phone,
                ] : null,
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
        });

        return $this->success($formatted);
    }
}
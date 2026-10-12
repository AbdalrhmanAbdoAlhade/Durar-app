<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->where('is_active', true)
            ->where(function ($q) {
                // غير محدود (null) أو الكمية متاحة
                $q->whereNull('quantity')->orWhere('quantity', '>', 0);
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->success($tickets);
    }

    /**
     * GET /api/tickets/{ticket}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->is_active, 404);

        return $this->success($ticket);
    }
}
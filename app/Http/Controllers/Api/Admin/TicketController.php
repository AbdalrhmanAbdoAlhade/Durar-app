<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Models\Ticket;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/admin/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->when($request->search, function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name_ar', 'like', $term)
                        ->orWhere('name_en', 'like', $term);
                });
            })
            ->when($request->has('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->success($tickets);
    }

    /**
     * POST /api/admin/tickets
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['quantity']) || $data['quantity'] < 0) {
            $data['quantity'] = null;
        }

        $data['discount_amount'] = $data['discount_amount'] ?? 0;
        $data['is_active']       = $data['is_active'] ?? true;

        $ticket = Ticket::create($data);

        return $this->success($ticket, 'تم إنشاء التذكرة بنجاح', 201);
    }

    /**
     * GET /api/admin/tickets/{ticket}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        return $this->success($ticket);
    }

    /**
     * PUT/PATCH /api/admin/tickets/{ticket}
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('quantity', $data)) {
            if ($data['quantity'] === null || $data['quantity'] < 0) {
                $data['quantity'] = null;
            }
        }

        $ticket->update($data);

        return $this->success($ticket->fresh(), 'تم تحديث التذكرة بنجاح');
    }

    /**
     * DELETE /api/admin/tickets/{ticket}
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();
        return $this->success(null, 'تم حذف التذكرة بنجاح');
    }

    /**
     * POST /api/admin/tickets/{ticket}/toggle-active
     */
    public function toggleActive(Ticket $ticket): JsonResponse
    {
        $ticket->update([
            'is_active' => ! $ticket->is_active,
        ]);

        return $this->success($ticket->fresh(), 'تم تغيير حالة التذكرة');
    }
}
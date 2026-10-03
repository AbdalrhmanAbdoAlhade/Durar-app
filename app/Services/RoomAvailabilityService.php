<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * حساب توافر أنواع الغرف (Room Types) بالتاريخ.
 *
 * المتاح = quantity - أعلى عدد غرف محجوزة في أي ليلة داخل الفترة المطلوبة.
 * (بنحسب بالليلة مش بمجموع الحجوزات المتداخلة، عشان حجزين مش متداخلين فعليًا ميتحسبوش كأنهم مع بعض)
 */
class RoomAvailabilityService
{
    /** الحجز pending (دفع أونلاين لسه ما اكتملش) بيحجز المخزون المدة دي بس، بعدها بيتحرر تلقائيًا */
    public const PENDING_HOLD_MINUTES = 30;

    /* ------------------------------------------------------------
     |  عدد الليالي (أقل حاجة ليلة)
     * ------------------------------------------------------------ */
    public function nights(Carbon $start, Carbon $end): int
    {
        return max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()));
    }

    /* ------------------------------------------------------------
     |  قراءة وفحص start_date / end_date من الـ Request
     |  returns [start|null, end|null, errors|null]
     * ------------------------------------------------------------ */
    public function stayFromRequest(Request $request, bool $required = false): array
    {
        $presence = $required ? 'required' : 'nullable';

        $v = Validator::make($request->only('start_date', 'end_date'), [
            'start_date' => "{$presence}|required_with:end_date|date_format:Y-m-d",
            'end_date'   => "{$presence}|required_with:start_date|date_format:Y-m-d|after:start_date",
        ]);

        if ($v->fails()) {
            return [null, null, $v->errors()];
        }

        if (!$request->filled('start_date')) {
            return [null, null, null];
        }

        return [
            Carbon::parse($request->input('start_date'))->startOfDay(),
            Carbon::parse($request->input('end_date'))->startOfDay(),
            null,
        ];
    }

    /* ------------------------------------------------------------
     |  الحجوزات اللي بتستهلك مخزون
     * ------------------------------------------------------------ */
    private function blockingBookings(array $roomIds, Carbon $start, Carbon $end, ?int $ignoreBookingId = null)
    {
        return RoomBooking::query()
            ->whereIn('room_id', $roomIds)
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString())
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'paid'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'pending')
                         ->where('created_at', '>=', now()->subMinutes(self::PENDING_HOLD_MINUTES));
                  });
            });
    }

    /* ------------------------------------------------------------
     |  أعلى عدد غرف محجوزة في ليلة واحدة لكل نوع — [room_id => peak]
     |  (استعلام واحد لكل الأنواع، مفيش N+1)
     * ------------------------------------------------------------ */
    public function peakBooked(array $roomIds, Carbon $start, Carbon $end, ?int $ignoreBookingId = null): array
    {
        $peaks = array_fill_keys($roomIds, 0);

        if (empty($roomIds)) {
            return $peaks;
        }

        $perNight = []; // [room_id][Y-m-d] => units

        $this->blockingBookings($roomIds, $start, $end, $ignoreBookingId)
            ->get(['id', 'room_id', 'start_date', 'end_date', 'number_of_rooms'])
            ->each(function ($b) use (&$perNight, $start, $end) {
                $from = Carbon::parse($b->start_date)->startOfDay();
                $to   = Carbon::parse($b->end_date)->startOfDay();

                if ($from->lt($start)) {
                    $from = $start->copy();
                }
                if ($to->gt($end)) {
                    $to = $end->copy();
                }

                $units = max(1, (int) $b->number_of_rooms);

                for ($d = $from->copy(); $d->lt($to); $d->addDay()) {
                    $key = $d->toDateString();
                    $perNight[$b->room_id][$key] = ($perNight[$b->room_id][$key] ?? 0) + $units;
                }
            });

        foreach ($perNight as $roomId => $nights) {
            $peaks[$roomId] = max($nights);
        }

        return $peaks;
    }

    /* ------------------------------------------------------------
     |  المتاح لنوع واحد
     * ------------------------------------------------------------ */
    public function availableUnits(Room $room, Carbon $start, Carbon $end, ?int $ignoreBookingId = null): int
    {
        $peak = $this->peakBooked([$room->id], $start, $end, $ignoreBookingId)[$room->id] ?? 0;

        return max(0, (int) $room->quantity - $peak);
    }

    /* ------------------------------------------------------------
     |  المتاح لمجموعة أنواع مرة واحدة — [room_id => available]
     * ------------------------------------------------------------ */
    public function availableForRooms(Collection $rooms, Carbon $start, Carbon $end): array
    {
        $peaks = $this->peakBooked($rooms->pluck('id')->all(), $start, $end);

        return $rooms->mapWithKeys(fn ($room) => [
            $room->id => max(0, (int) $room->quantity - ($peaks[$room->id] ?? 0)),
        ])->all();
    }

    /* ------------------------------------------------------------
     |  أعلى حجز قادم (من النهارده لسنتين) — بنستخدمه قبل تقليل الكمية أو حذف النوع
     * ------------------------------------------------------------ */
    public function peakBookedFromToday(int $roomId): int
    {
        $from = now()->startOfDay();
        $to   = now()->addYears(2)->startOfDay();

        return $this->peakBooked([$roomId], $from, $to)[$roomId] ?? 0;
    }
}

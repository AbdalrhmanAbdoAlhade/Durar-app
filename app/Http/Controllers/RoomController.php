<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * الـ Room هنا = "نوع غرفة" (Room Type) له quantity.
 * صاحب الفندق بيدخل النوع مرة واحدة (مثلاً: غرفة فردية × 100) بدل 100 صف.
 */
class RoomController extends Controller
{
    /** تصنيفات الغرف المسموحة */
    private const TYPES = [
        'standard', 'single', 'double', 'twin', 'triple',
        'quad', 'family', 'suite', 'deluxe', 'presidential',
    ];

    public function __construct(private RoomAvailabilityService $availability)
    {
    }

    /**
     * helper: توحيد شكل الحقل المترجم
     */
    private function normalizeTranslation($value): array
    {
        if (is_array($value)) {
            return array_filter([
                'ar' => $value['ar'] ?? null,
                'en' => $value['en'] ?? null,
            ]);
        }

        return [app()->getLocale() => $value];
    }

    /**
     * helper: أدمن / صاحب الفندق / موظف عنده الصلاحية
     */
    private function canManageHotel($user, ?Hotel $hotel, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        return $user->role === 'admin'
            || ($hotel && (int) $hotel->user_id === (int) $user->id)
            || $user->hasPermission($permission);
    }

    /**
     * helper: إضافة available_units (لو التواريخ موجودة) على مجموعة أنواع بـ query واحد
     */
    private function attachAvailability(Collection $rooms, ?Carbon $start, ?Carbon $end): void
    {
        $map    = $start ? $this->availability->availableForRooms($rooms, $start, $end) : [];
        $nights = $start ? $this->availability->nights($start, $end) : null;

        foreach ($rooms as $room) {
            $room->setAttribute('available_units', $start ? ($map[$room->id] ?? 0) : null);

            if ($start) {
                $room->setAttribute('stay_nights', $nights);
                $room->setAttribute('stay_price_per_unit', round($nights * (float) $room->price_per_night, 2));
            }
        }
    }

    /**
     * helper: تحديث ملخص الفندق (إجمالي الغرف / عدد السويت / أقل سعر) من أنواع الغرف
     * عشان الأرقام اللي بتظهر في كارت الفندق تفضل مطابقة للأنواع.
     */
    private function syncHotelSummary(int $hotelId): void
    {
        $types = Room::where('hotel_id', $hotelId)->where('is_active', true);

        $update = [];

        if (Schema::hasColumn('hotels', 'rooms')) {
            $update['rooms'] = (int) (clone $types)->sum('quantity');
        }

        if (Schema::hasColumn('hotels', 'suites_count')) {
            $update['suites_count'] = (int) (clone $types)->where('room_type', 'suite')->sum('quantity');
        }

        $minPrice = (clone $types)->min('price_per_night');
        if ($minPrice !== null && Schema::hasColumn('hotels', 'price_per_night')) {
            $update['price_per_night'] = $minPrice;
        }

        if (!empty($update)) {
            DB::table('hotels')->where('id', $hotelId)->update($update);
        }
    }

    /* ============================================================
     |  STORE  (نوع غرفة جديد + الكمية)
     * ============================================================ */
    public function store(Request $request)
    {
        $user = Auth::user();

        // ✅ مسموح لصاحب الفندق / شركة / أدمن / موظف عنده صلاحية hotels.create
        $hasRoleAccess       = in_array($user->role, ['hotel_owner', 'company_owner', 'admin']);
        $hasPermissionAccess = $user->hasPermission('hotels.create');

        if (!$hasRoleAccess && !$hasPermissionAccess) {
            return response()->json(['message' => __('responses.unauthorized')], 403);
        }

        $validator = Validator::make($request->all(), [
            'hotel_id'        => 'required|exists:hotels,id',
            'name'            => 'required',
            'room_type'       => 'required|in:' . implode(',', self::TYPES),
            'quantity'        => 'required|integer|min:1|max:5000',
            'max_guests'      => 'nullable|integer|min:1|max:50',
            'is_active'       => 'nullable|boolean',
            'cover_image'     => 'required|image|max:20048',
            'images.*'        => 'image|max:20048',
            'details'         => 'nullable',
            'size'            => 'nullable|string',
            'facilities'      => 'nullable',
            'description'     => 'nullable',
            'floor_number'    => 'nullable|string',
            'room_number'     => 'nullable|string',
            'price_per_night' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // ✅ لازم يكون الفندق بتاعه (أو أدمن / موظف بصلاحية)
        $hotel = Hotel::find($request->hotel_id);

        if (!$this->canManageHotel($user, $hotel, 'hotels.create')) {
            return response()->json(['message' => __('responses.unauthorized')], 403);
        }

        $coverImagePath = $request->file('cover_image')->store('rooms/covers', 'public');
        $coverImagePath = '/' . $coverImagePath;

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path     = $image->store('rooms/images', 'public');
                $images[] = '/' . $path;
            }
        }

        $room = new Room();

        $room->setTranslations('name', $this->normalizeTranslation($request->input('name')));

        if ($request->filled('description')) {
            $room->setTranslations('description', $this->normalizeTranslation($request->input('description')));
        }

        if ($request->filled('details')) {
            $room->setTranslations('details', $this->normalizeTranslation($request->input('details')));
        }

        if ($request->filled('facilities')) {
            $room->setTranslations('facilities', $this->normalizeTranslation($request->input('facilities')));
        }

        $room->hotel_id        = $request->hotel_id;
        $room->room_type       = $request->room_type;
        $room->quantity        = (int) $request->quantity;
        $room->max_guests      = $request->max_guests;
        $room->is_active       = $request->boolean('is_active', true);
        $room->cover_image     = $coverImagePath;
        $room->images          = $images;
        $room->size            = $request->size;
        $room->room_number     = $request->room_number;
        $room->floor_number    = $request->floor_number;
        $room->price_per_night = $request->price_per_night;

        $room->save();

        $this->syncHotelSummary((int) $room->hotel_id);

        $room->setAttribute('available_units', $room->quantity);

        return response()->json($room, 201);
    }

    /* ============================================================
     |  INDEX
     |  ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD  => available_units لكل نوع
     * ============================================================ */
    public function index(Request $request)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $user = Auth::user();

        $rooms = Room::visibleTo($user)
            ->with('hotel')
            ->when(!$user || $user->role !== 'admin', fn ($q) => $q->where('is_active', true))
            ->get();

        foreach ($rooms as $room) {
            if (
                $room->hotel &&
                is_array($room->hotel->cover_image) &&
                !empty($room->hotel->cover_image)
            ) {
                foreach ($room->hotel->cover_image as $key => $image) {
                    if (!str_starts_with($image, '/storage/')) {
                        $room->hotel->cover_image[$key] = '/storage/' . $image;
                    }
                }
            }
        }

        $this->attachAvailability($rooms, $start, $end);

        return response()->json($rooms);
    }

    /* ============================================================
     |  SHOW
     * ============================================================ */
    public function show(Request $request, $id)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $user = Auth::user();

        $room = Room::visibleTo($user)
            ->with('hotel')
            ->find($id);

        if (!$room) {
            return response()->json(['error' => __('responses.room_not_found')], 404);
        }

        // النوع المخفي يظهر بس لمدير الفندق
        if (!$room->is_active && !$this->canManageHotel($user, $room->hotel, 'hotels.update')) {
            return response()->json(['error' => __('responses.room_not_found')], 404);
        }

        $this->attachAvailability(collect([$room]), $start, $end);

        return response()->json($room);
    }

    /* ============================================================
     |  AVAILABILITY  (توافر نوع معين لفترة معينة)
     |  GET /rooms/{id}/availability?start_date&end_date&number_of_rooms
     * ============================================================ */
    public function availability(Request $request, $id)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request, true);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $room = Room::visibleTo(Auth::user())->find($id);

        if (!$room) {
            return response()->json(['error' => __('responses.room_not_found')], 404);
        }

        $requested = max(1, (int) $request->input('number_of_rooms', 1));
        $available = $this->availability->availableUnits($room, $start, $end);
        $nights    = $this->availability->nights($start, $end);

        return response()->json([
            'status'          => true,
            'room_id'         => $room->id,
            'room_type'       => $room->room_type,
            'quantity'        => (int) $room->quantity,
            'available_units' => $available,
            'requested_units' => $requested,
            'can_book'        => $room->is_active && $available >= $requested,
            'nights'          => $nights,
            'price_per_night' => (float) $room->price_per_night,
            'total_price'     => round($nights * (float) $room->price_per_night * $requested, 2),
        ]);
    }

    /* ============================================================
     |  UPDATE
     * ============================================================ */
    public function update(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {

            // قفل الصف عشان مفيش حجز يتعمل في نفس اللحظة اللي بتتغير فيها الكمية
            $room = Room::whereKey($id)->lockForUpdate()->first();

            if (!$room) {
                return response()->json(['error' => __('responses.room_not_found')], 404);
            }

            $user = Auth::user();

            // ✅ مسموح لصاحب الفندق / شركة / أدمن / موظف عنده صلاحية hotels.update
            if (!$this->canManageHotel($user, $room->hotel, 'hotels.update')) {
                return response()->json(['message' => __('responses.unauthorized')], 403);
            }

            $validator = Validator::make($request->all(), [
                'name'            => 'sometimes',
                'room_type'       => 'sometimes|in:' . implode(',', self::TYPES),
                'quantity'        => 'sometimes|integer|min:1|max:5000',
                'max_guests'      => 'nullable|integer|min:1|max:50',
                'is_active'       => 'sometimes|boolean',
                'cover_image'     => 'nullable|image|max:20048',
                'images.*'        => 'image|max:20048',
                'details'         => 'nullable',
                'size'            => 'nullable|string',
                'facilities'      => 'nullable',
                'description'     => 'nullable',
                'price_per_night' => 'sometimes|required|numeric',
                'floor_number'    => 'nullable|string',
                'room_number'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // ✅ تقليل الكمية: ممنوع تنزل تحت أعلى حجز قادم
            if ($request->has('quantity')) {
                $newQuantity = (int) $request->quantity;

                if ($newQuantity < (int) $room->quantity) {
                    $peak = $this->availability->peakBookedFromToday($room->id);

                    if ($newQuantity < $peak) {
                        return response()->json([
                            'status'  => false,
                            'message' => __('rooms.quantity_below_booked', [
                                'quantity' => $newQuantity,
                                'booked'   => $peak,
                            ]),
                            'booked_peak' => $peak,
                        ], 422);
                    }
                }

                $room->quantity = $newQuantity;
            }

            if ($request->has('room_type')) {
                $room->room_type = $request->room_type;
            }

            if ($request->has('max_guests')) {
                $room->max_guests = $request->max_guests;
            }

            if ($request->has('is_active')) {
                $room->is_active = $request->boolean('is_active');
            }

            // دمج الترجمات
            if ($request->has('name')) {
                $existing = $room->getTranslations('name');
                $incoming = $this->normalizeTranslation($request->input('name'));
                $room->setTranslations('name', array_merge($existing, $incoming));
            }

            if ($request->has('description')) {
                $existing = $room->getTranslations('description');
                $incoming = $this->normalizeTranslation($request->input('description'));
                $room->setTranslations('description', array_merge($existing, $incoming));
            }

            if ($request->has('details')) {
                $existing = $room->getTranslations('details');
                $incoming = $this->normalizeTranslation($request->input('details'));
                $room->setTranslations('details', array_merge($existing, $incoming));
            }

            if ($request->has('facilities')) {
                $existing = $room->getTranslations('facilities');
                $incoming = $this->normalizeTranslation($request->input('facilities'));
                $room->setTranslations('facilities', array_merge($existing, $incoming));
            }

            // صورة الكفر
            if ($request->hasFile('cover_image')) {
                if ($room->cover_image) {
                    Storage::disk('public')->delete(ltrim($room->cover_image, '/'));
                }
                $coverImagePath    = $request->file('cover_image')->store('rooms/covers', 'public');
                $room->cover_image = '/' . $coverImagePath;
            }

            // الصور الإضافية
            if ($request->hasFile('images')) {
                if (is_array($room->images)) {
                    foreach ($room->images as $oldImage) {
                        Storage::disk('public')->delete(ltrim($oldImage, '/'));
                    }
                }

                $images = [];
                foreach ($request->file('images') as $image) {
                    $path     = $image->store('rooms/images', 'public');
                    $images[] = '/' . $path;
                }
                $room->images = $images;
            }

            // باقي الحقول
            $room->fill($request->only([
                'size', 'room_number', 'floor_number', 'price_per_night',
            ]));

            $room->save();

            $this->syncHotelSummary((int) $room->hotel_id);

            return response()->json($room->fresh()->load('hotel'));
        });
    }

    /* ============================================================
     |  DESTROY
     * ============================================================ */
    public function destroy($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['error' => __('responses.room_not_found')], 404);
        }

        $user = Auth::user();

        // ✅ مسموح لصاحب الفندق / شركة / أدمن / موظف عنده صلاحية hotels.delete
        if (!$this->canManageHotel($user, $room->hotel, 'hotels.delete')) {
            return response()->json(['message' => __('responses.unauthorized')], 403);
        }

        // ✅ ممنوع الحذف لو في حجوزات قادمة (الأفضل تخفيه بـ is_active=false)
        if ($this->availability->peakBookedFromToday($room->id) > 0) {
            return response()->json([
                'status'  => false,
                'message' => __('rooms.has_upcoming_bookings'),
            ], 422);
        }

        // حذف الصور
        if ($room->cover_image) {
            Storage::disk('public')->delete(ltrim($room->cover_image, '/'));
        }
        if (is_array($room->images)) {
            foreach ($room->images as $img) {
                Storage::disk('public')->delete(ltrim($img, '/'));
            }
        }

        $hotelId = (int) $room->hotel_id;

        $room->delete();

        $this->syncHotelSummary($hotelId);

        return response()->json(['message' => __('responses.room_deleted')]);
    }

    /* ============================================================
     |  Room Types by Hotel
     |  ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD  => available_units لكل نوع
     * ============================================================ */
    public function getRoomsByHotel(Request $request, $hotel_id)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $user  = Auth::user();
        $hotel = Hotel::find($hotel_id);

        // مدير الفندق يشوف الأنواع المخفية كمان
        $canManage = $this->canManageHotel($user, $hotel, 'hotels.update');

        $rooms = Room::visibleTo($user)
            ->where('hotel_id', $hotel_id)
            ->with('hotel')
            ->when(!$canManage, fn ($q) => $q->where('is_active', true))
            ->orderBy('price_per_night')
            ->get();

        if ($rooms->isEmpty()) {
            return response()->json(['message' => __('responses.no_rooms_for_hotel')], 404);
        }

        $this->attachAvailability($rooms, $start, $end);

        return response()->json([
            'status'      => 'success',
            'hotel_id'    => $hotel_id,
            'total_units' => (int) $rooms->sum('quantity'),
            'rooms'       => $rooms,
        ], 200);
    }
}

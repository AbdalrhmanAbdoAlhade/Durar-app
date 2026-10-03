<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{
    public function __construct(private RoomAvailabilityService $availability)
    {
    }

    /* ============================================================
     |  helper: توحيد شكل الحقل المترجم
     * ============================================================ */
    private function normalizeTranslation($value): array
    {
        if (is_array($value)) {
            return array_filter([
                'ar' => $value['ar'] ?? null,
                'en' => $value['en'] ?? null,
                'ur' => $value['ur'] ?? null,
                'tr' => $value['tr'] ?? null,
                'id' => $value['id'] ?? null,
            ]);
        }

        return [app()->getLocale() => $value];
    }

    /* ============================================================
     |  helper: أنواع الغرف (Eager load) — أعمدة خفيفة + النشطة بس + الأرخص الأول
     |  الفندق بيرجع مرة واحدة وجواه room_types (مش 100 غرفة)
     * ============================================================ */
    private function roomTypesEagerLoad(): array
    {
        return [
            'roomTypes' => fn ($q) => $q->where('is_active', true)
                ->select([
                    'id', 'hotel_id', 'name', 'room_type', 'cover_image', 'size',
                    'max_guests', 'price_per_night', 'quantity',
                ])
                ->orderBy('price_per_night'),
        ];
    }

    /* ============================================================
     |  helper: ملخص الأنواع + التوافر (لو التواريخ موجودة) على مجموعة فنادق بـ query واحد
     * ============================================================ */
    private function decorateWithRoomTypes(Collection $hotels, ?Carbon $start, ?Carbon $end): void
    {
        $allTypes  = $hotels->flatMap(fn ($hotel) => $hotel->roomTypes);
        $available = $start ? $this->availability->availableForRooms($allTypes, $start, $end) : [];
        $nights    = $start ? $this->availability->nights($start, $end) : null;

        foreach ($hotels as $hotel) {
            foreach ($hotel->roomTypes as $type) {
                $type->setAttribute('available_units', $start ? ($available[$type->id] ?? 0) : null);

                if ($start) {
                    $type->setAttribute('stay_nights', $nights);
                    $type->setAttribute('stay_price_per_unit', round($nights * (float) $type->price_per_night, 2));
                }
            }

            $hotel->setAttribute('total_room_units', (int) $hotel->roomTypes->sum('quantity'));
            $hotel->setAttribute('min_room_price', $hotel->roomTypes->min('price_per_night'));

            if ($start) {
                $hotel->setAttribute(
                    'has_availability',
                    $hotel->roomTypes->contains(fn ($type) => $type->available_units > 0)
                );
            }
        }
    }

    /* ============================================================
     |  PENDING HOTELS (للأدمن)
     * ============================================================ */
    public function pendingHotels(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthorized'),
            ], 403);
        }

        $hotels = Hotel::where('status', 'pending')
            ->with('user:id,name,email,phone,status,registration_role')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'total'  => $hotels->count(),
            'data'   => $hotels,
        ]);
    }

    /* ============================================================
     |  UPDATE STATUS (للأدمن)
     * ============================================================ */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthorized'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.hotel_not_found'),
            ], 404);
        }

        $newStatus = $request->input('status');
        $hotel->update(['status' => $newStatus]);

        // ✅ تحويل role و status الـ user (لو اتقبل)
        if ($newStatus === 'approved' && $hotel->user) {
            if (in_array($hotel->user->role, ['user', 'employee'])) {
                $hotel->user->update([
                    'role'   => 'hotel_owner',
                    'status' => 'active',
                ]);
            }
        }

        return response()->json([
            'status'  => true,
            'message' => $newStatus === 'approved'
                ? 'تم قبول الفندق بنجاح.'
                : 'تم رفض الفندق.',
            'data'    => $hotel->fresh()->load('user:id,name,email,role,status'),
        ]);
    }

    /* ============================================================
     |  فلترة خاصة
     * ============================================================ */

    public function hotelsByBookings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $limit = $request->limit ?: 10;

        $hotels = Hotel::visibleTo(Auth::user())
            ->withCount(['rooms as bookings_count' => function ($query) {
                $query->select(DB::raw('count(*)'))
                      ->join('room_bookings', 'rooms.id', '=', 'room_bookings.room_id');
            }])
            ->having('bookings_count', '>', 0)
            ->orderBy('bookings_count', 'desc')
            ->limit($limit)
            ->get();

        if ($hotels->isEmpty()) {
            return response()->json(['message' => __('responses.no_hotels_found_with_bookings')], 404);
        }

        return response()->json($hotels);
    }

    public function hotelsByStars(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stars' => 'required|numeric|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $stars = $request->stars;

        $hotels = Hotel::visibleTo(Auth::user())
            ->withAvg('reviews', 'stars')
            ->having('reviews_avg_stars', '>=', $stars)
            ->orderByDesc('reviews_avg_stars')
            ->get();

        if ($hotels->isEmpty()) {
            return response()->json(['message' => __('responses.hotel_not_found_with_rating')], 404);
        }

        return response()->json($hotels);
    }

    public function nearbyHotels(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $latitude  = $request->latitude;
        $longitude = $request->longitude;

        $hotels = Hotel::visibleTo(Auth::user())
            ->select('*', DB::raw("(
                6371 * acos(
                    cos(radians($latitude)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians($longitude)) +
                    sin(radians($latitude)) * sin(radians(latitude))
                )
            ) AS distance"))
            ->orderBy('distance', 'asc')
            ->get();

        if ($hotels->isEmpty()) {
            return response()->json(['message' => __('responses.hotel_not_found')], 404);
        }

        return response()->json($hotels);
    }

    /* ============================================================
     |  CRUD
     * ============================================================ */

    /**
     * ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD  => available_units لكل نوع + has_availability للفندق
     */
    public function index(Request $request)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $query = Hotel::visibleTo(Auth::user())
            ->with('user:id,name,email,phone')
            ->with($this->roomTypesEagerLoad());

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $hotels = $query->get();

        $this->decorateWithRoomTypes($hotels, $start, $end);

        return response()->json([
            'message' => $request->filled('user_id')
                ? __('responses.user_hotels_list')
                : __('responses.all_hotels_list'),
            'hotels'  => $hotels,
        ]);
    }

    public function show(Request $request, $id)
    {
        [$start, $end, $errors] = $this->availability->stayFromRequest($request);

        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $hotel = Hotel::visibleTo(Auth::user())
            ->with('user:id,name,email,phone')
            ->with($this->roomTypesEagerLoad())
            ->find($id);

        if (!$hotel) {
            return response()->json(['message' => __('responses.hotel_not_found')], 404);
        }

        $this->decorateWithRoomTypes(collect([$hotel]), $start, $end);

        return response()->json([
            'message' => __('responses.hotel_details'),
            'hotel'   => $hotel,
        ]);
    }

    /* ------------------------------------------------------------
     |  STORE
     * ------------------------------------------------------------ */
    public function store(Request $request)
    {
        $user = Auth::user();

        $hasRoleAccess = in_array($user->role, ['hotel_owner', 'company_owner', 'admin']);
        $hasPermissionAccess = $user->hasPermission('hotels.create');

        if (!$hasRoleAccess && !$hasPermissionAccess) {
            return response()->json([
                'message' => __('responses.unauthorized_hotel_creation'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            // ✅ الترجمات
            'name'                => 'required|array',
            'name.ar'             => 'required|string',
            'name.en'             => 'nullable|string',
            'name.ur'             => 'nullable|string',
            'name.tr'             => 'nullable|string',
            'name.id'             => 'nullable|string',

            'description'         => 'required|array',
            'description.ar'      => 'required|string',
            'description.en'      => 'nullable|string',
            'description.ur'      => 'nullable|string',
            'description.tr'      => 'nullable|string',
            'description.id'      => 'nullable|string',

            'city'                => 'nullable|array',
            'city.ar'             => 'nullable|string',
            'city.en'             => 'nullable|string',
            'city.ur'             => 'nullable|string',
            'city.tr'             => 'nullable|string',
            'city.id'             => 'nullable|string',

            'address'             => 'required|array',
            'address.ar'          => 'required|string',
            'address.en'          => 'nullable|string',
            'address.ur'          => 'nullable|string',
            'address.tr'          => 'nullable|string',
            'address.id'          => 'nullable|string',

            // الصور
            'images'              => 'required|array',
            'images.*'            => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'cover_images'        => 'required|array',
            'cover_images.*'      => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',

            // بيانات الفندق
            'stars'               => 'required|numeric',
            'country'             => 'required|string',
            'pay_on_arrival_enabled' => 'nullable|boolean',

            'details'             => 'required|array',
            'details.room_size'   => 'required|string',
            'details.bathrooms'   => 'required|string',
            'details.bedrooms'    => 'required|string',
            'details.bed_type'    => 'required|string',

            'facilities'          => 'required|array',
            'latitude'            => 'required|numeric',
            'longitude'           => 'required|numeric',
            'price_per_night'     => 'nullable|numeric',

            'property_type_id'    => 'nullable|exists:property_types,id',
            'property_type'       => 'nullable|string',
            'area'                => 'nullable|string',
            'rooms'               => 'nullable|integer',
            'suites_count'        => 'nullable|integer|min:0',

            // ✅ الحقول الجديدة
            'national_id'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'phone'               => 'nullable|string|max:30',
            'ownership_deed'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'commercial_register' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'tax_certificate'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // رفع الصور
        $coverImages = collect($request->cover_images)->map(function ($cover) {
            return '/storage/' . $cover->store('hotels/covers', 'public');
        });

        $images = collect($request->images)->map(function ($image) {
            return '/storage/' . $image->store('hotels/images', 'public');
        });

        // استثناء كل الحقول الملفات من الإنشاء التلقائي
        $hotel = new Hotel($request->except([
            'images', 'cover_images',
            'national_id',
            'ownership_deed', 'commercial_register', 'tax_certificate',
            'status', 'name', 'description', 'city', 'address',
        ]));

        $hotel->setTranslations('name', $this->normalizeTranslation($request->input('name')));
        $hotel->setTranslations('description', $this->normalizeTranslation($request->input('description')));
        $hotel->setTranslations('address', $this->normalizeTranslation($request->input('address')));

        if ($request->filled('city')) {
            $hotel->setTranslations('city', $this->normalizeTranslation($request->input('city')));
        }

        $hotel->images      = $images;
        $hotel->cover_image = $coverImages;
        $hotel->user_id     = $user->id;
        $hotel->status      = 'pending';
        $hotel->facilities  = $request->facilities;
        $hotel->details     = $request->details;

        // رفع الوثائق (national_id + الباقي)
        $docFields = ['national_id', 'ownership_deed', 'commercial_register', 'tax_certificate'];
        foreach ($docFields as $field) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store('hotels/documents', 'public');
                $hotel->$field = '/storage/' . $path;
            }
        }

        $hotel->save();

        return response()->json($hotel, 201);
    }

    /* ------------------------------------------------------------
     |  UPDATE
     * ------------------------------------------------------------ */
    public function update(Request $request, $id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json(['message' => __('responses.hotel_not_found')], 404);
        }

        $user = Auth::user();

        $isOwner = $hotel->user_id == $user->id;
        $hasPermissionAccess = $user->hasPermission('hotels.update');

        if (!$isOwner && $user->role !== 'admin' && !$hasPermissionAccess) {
            return response()->json(['message' => __('responses.unauthorized')], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|array',
            'name.ar'       => 'sometimes|string',
            'name.en'       => 'nullable|string',
            'name.ur'       => 'nullable|string',
            'name.tr'       => 'nullable|string',
            'name.id'       => 'nullable|string',

            'description'   => 'sometimes|array',
            'description.ar'=> 'sometimes|string',
            'description.en'=> 'nullable|string',
            'description.ur'=> 'nullable|string',
            'description.tr'=> 'nullable|string',
            'description.id'=> 'nullable|string',

            'city'          => 'sometimes|array',
            'city.ar'       => 'nullable|string',
            'city.en'       => 'nullable|string',
            'city.ur'       => 'nullable|string',
            'city.tr'       => 'nullable|string',
            'city.id'       => 'nullable|string',

            'address'       => 'sometimes|array',
            'address.ar'    => 'sometimes|string',
            'address.en'    => 'nullable|string',
            'address.ur'    => 'nullable|string',
            'address.tr'    => 'nullable|string',
            'address.id'    => 'nullable|string',

            'suites_count'  => 'nullable|integer|min:0',

            // ✅ الحقول الجديدة
            'national_id'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'phone'               => 'nullable|string|max:30',
            'ownership_deed'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'commercial_register' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'tax_certificate'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // دمج الترجمات
        if ($request->has('name')) {
            $existing = $hotel->getTranslations('name');
            $incoming = $this->normalizeTranslation($request->input('name'));
            $hotel->setTranslations('name', array_merge($existing, $incoming));
        }

        if ($request->has('description')) {
            $existing = $hotel->getTranslations('description');
            $incoming = $this->normalizeTranslation($request->input('description'));
            $hotel->setTranslations('description', array_merge($existing, $incoming));
        }

        if ($request->has('city')) {
            $existing = $hotel->getTranslations('city');
            $incoming = $this->normalizeTranslation($request->input('city'));
            $hotel->setTranslations('city', array_merge($existing, $incoming));
        }

        if ($request->has('address')) {
            $existing = $hotel->getTranslations('address');
            $incoming = $this->normalizeTranslation($request->input('address'));
            $hotel->setTranslations('address', array_merge($existing, $incoming));
        }

        // 1. الصور
        $imageFields = ['cover_images' => 'cover_image', 'images' => 'images'];

        foreach ($imageFields as $requestKey => $dbColumn) {
            if ($request->hasFile($requestKey)) {
                if (is_array($hotel->$dbColumn)) {
                    foreach ($hotel->$dbColumn as $oldImage) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $oldImage));
                    }
                }

                $files = $request->file($requestKey);
                $filesArray = is_array($files) ? $files : [$files];

                $hotel->$dbColumn = array_map(function ($file) use ($dbColumn) {
                    $path = $file->store(
                        'hotels/' . ($dbColumn === 'images' ? 'images' : 'covers'),
                        'public'
                    );
                    return '/storage/' . $path;
                }, $filesArray);
            }
        }

        // 2. الوثائق الفردية (national_id + الباقي)
        $docFields = ['national_id', 'ownership_deed', 'commercial_register', 'tax_certificate'];
        foreach ($docFields as $field) {
            if ($request->hasFile($field)) {
                if ($hotel->$field) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $hotel->$field));
                }
                $hotel->$field = '/storage/' . $request->file($field)->store('hotels/documents', 'public');
            }
        }

        // 3. باقي الحقول (استثناء الملفات)
        $dataToFill = $request->except([
            'images', 'cover_images',
            'national_id',
            'ownership_deed', 'commercial_register', 'tax_certificate',
            'name', 'description', 'city', 'address',
        ]);

        $hotel->fill($dataToFill);
        $hotel->save();

        return response()->json($hotel->fresh());
    }

    /* ------------------------------------------------------------
     |  DESTROY
     * ------------------------------------------------------------ */
    public function destroy($id)
    {
        $hotel = Hotel::find($id);

        if (!$hotel) {
            return response()->json(['message' => __('responses.hotel_not_found')], 404);
        }

        $user = Auth::user();

        $isOwner = $hotel->user_id == $user->id;
        $hasPermissionAccess = $user->hasPermission('hotels.delete');

        if (!$isOwner && $user->role !== 'admin' && !$hasPermissionAccess) {
            return response()->json(['message' => __('responses.unauthorized')], 403);
        }

        // حذف الصور
        if (is_array($hotel->cover_image)) {
            foreach ($hotel->cover_image as $image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $image));
            }
        }

        if (is_array($hotel->images)) {
            foreach ($hotel->images as $image) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $image));
            }
        }

        // حذف الوثائق
        foreach (['national_id', 'ownership_deed', 'commercial_register', 'tax_certificate'] as $field) {
            if ($hotel->$field) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $hotel->$field));
            }
        }

        $hotel->delete();

        return response()->json(['message' => __('responses.hotel_deleted')]);
    }
}

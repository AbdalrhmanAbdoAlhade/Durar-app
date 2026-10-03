<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\RoomBooking;
use App\Models\User;
use App\Services\BookingNotificationService;
use App\Services\RoomAvailabilityService;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomBookingController extends Controller
{
    public function __construct(private RoomAvailabilityService $availability)
    {
    }

    /* ============================================================
     |  Helper: send notification safely (never breaks main flow)
     * ============================================================ */
    private function safeNotify(callable $callback): void
    {
        try {
            $callback();
        } catch (\Exception $e) {
            Log::warning('Notification failed: ' . $e->getMessage());
        }
    }

    /* ============================================================
     |  فلترة
     * ============================================================ */
    public function getBookingsByHotelOwner($userId)
    {
        $owner = User::find($userId);
        if (!$owner) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.user_not_found'),
            ], 404);
        }

        $bookings = RoomBooking::whereHas('room.hotel', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['room.hotel'])
            ->latest()
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status'  => true,
                'message' => __('responses.no_bookings_for_hotel'),
                'data'    => [],
            ]);
        }

        $data = $bookings->map(function ($booking) {
            return [
                'booking_id'   => $booking->id,
                'guest_name'   => $booking->name,
                'guest_phone'  => $booking->phone,
                'status'       => $booking->status,
                'start_date'   => $booking->start_date,
                'end_date'     => $booking->end_date,
                'room_number'  => $booking->room_number,
                'hotel_name'   => $booking->room->hotel->name ?? 'غير معروف',
                'hotel_id'     => $booking->room->hotel->id ?? null,
            ];
        });

        return response()->json([
            'status'         => true,
            'owner_id'       => $userId,
            'total_bookings' => $data->count(),
            'data'           => $data,
        ]);
    }

    /* ============================================================
     |  Book Room  (حجز X غرفة من نوع معين)
     * ============================================================ */
    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id'              => 'required|exists:rooms,id',
            'required_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            'start_date'           => 'required|date|after_or_equal:today',
            'end_date'             => 'required|date|after:start_date',
            'number_of_rooms'      => 'nullable|integer|min:1',
            'number_of_guests'     => 'nullable|integer|min:1',
            'adults'               => 'required|integer|min:0',
            'children'             => 'required|integer|min:0',
            'name'                 => 'required|string',
            'date_of_birth'        => 'required|string',
            'national_id'          => 'required|string',
            'email'                => 'required|email',
            'phone'                => 'required|string',
            'title'                => 'required|in:Mr,Mrs,Miss',
            'payment_method'       => 'nullable|in:online,on_arrival',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room  = Room::with('hotel')->findOrFail($request->room_id);
        $hotel = $room->hotel;

        if (!$hotel) {
            return response()->json(['message' => __('responses.room_not_linked_to_hotel')], 404);
        }

        // طريقة الدفع + التحقق من صلاحية "الدفع عند الوصول"
        $paymentMethod = $request->input('payment_method', 'online');

        if ($paymentMethod === 'on_arrival' && !$hotel->pay_on_arrival_enabled) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.pay_on_arrival_not_available_for_this_hotel'),
            ], 422);
        }

        // حساب السعر
        $start         = Carbon::parse($request->start_date)->startOfDay();
        $end           = Carbon::parse($request->end_date)->startOfDay();
        $numberOfRooms = (int) ($request->number_of_rooms ?? 1);
        $days          = $this->availability->nights($start, $end);
        $totalPrice    = $days * $room->price_per_night * $numberOfRooms;

        // ✅ السعة: عدد الضيوف مقابل max_guests × عدد الغرف
        if ($room->max_guests) {
            $totalGuests = (int) $request->adults + (int) $request->children;

            if ($totalGuests > $room->max_guests * $numberOfRooms) {
                return response()->json([
                    'status'  => false,
                    'message' => __('rooms.exceeds_capacity', ['max' => $room->max_guests]),
                ], 422);
            }
        }

        // توليد الباسوردات
        $roomPassword = strtoupper('NL' . rand(1000, 9999)) . '@';
        $mainPassword = strtoupper('GH' . rand(100, 999)) . '!@' . rand(1, 9);

        // الحالة الابتدائية
        $initialStatus = ($paymentMethod === 'on_arrival') ? 'confirmed' : 'pending';

        // ✅ فحص التوافر + إنشاء الحجز في transaction واحدة مع قفل صف النوع
        //    (يمنع حجزين متزامنين من إنهم ياخدوا آخر غرفة)
        $result = DB::transaction(function () use (
            $room, $hotel, $start, $end, $numberOfRooms, $totalPrice,
            $request, $paymentMethod, $initialStatus, $roomPassword, $mainPassword
        ) {
            $locked = Room::whereKey($room->id)->lockForUpdate()->first();

            if (!$locked || !$locked->is_active) {
                return response()->json([
                    'status'  => false,
                    'message' => __('rooms.type_inactive'),
                ], 422);
            }

            $available = $this->availability->availableUnits($locked, $start, $end);

            if ($available < $numberOfRooms) {
                return response()->json([
                    'status'          => false,
                    'code'            => 'ROOM_TYPE_NOT_ENOUGH_UNITS',
                    'message'         => __('rooms.not_enough_units', ['available' => $available]),
                    'available_units' => $available,
                ], 409);
            }

            return RoomBooking::create([
                'uuid'             => (string) Str::uuid(),
                'room_id'          => $locked->id,
                'hotel_id'         => $hotel->id,
                'user_id'          => Auth::id(),
                'start_date'       => $request->start_date,
                'end_date'         => $request->end_date,
                'number_of_rooms'  => $numberOfRooms,
                'number_of_guests' => $request->number_of_guests,
                'adults'           => $request->adults,
                'children'         => $request->children,
                'total_price'      => $totalPrice,
                'name'             => $request->name,
                'date_of_birth'    => $request->date_of_birth,
                'national_id'      => $request->national_id,
                'email'            => $request->email,
                'phone'            => $request->phone,
                'title'            => $request->title,
                'room_number'      => $locked->room_number,
                'floor_number'     => $locked->floor_number,
                'room_password'    => $roomPassword,
                'main_password'    => $mainPassword,
                'status'           => $initialStatus,
                'payment_method'   => $paymentMethod,
            ]);
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $booking = $result;

        // رفع الملفات (بعد ما الحجز اتأكد عشان منسيبش ملفات يتيمة لو الغرف خلصت)
        $uploadedDocuments = [];
        if ($request->hasFile('required_documents')) {
            foreach ($request->file('required_documents') as $file) {
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('public/required_documents', $filename);
                $uploadedDocuments[] = url('storage/required_documents/' . $filename);
            }

            $booking->update(['required_documents' => $uploadedDocuments]);
        }

        // ✅ إشعار صاحب الفندق بالحجز الجديد
        $this->safeNotify(function () use ($booking) {
            app(BookingNotificationService::class)->notifyOwnerNewRoomBooking($booking);
        });

        // ✅ لو دفع عند الوصول → رجّع الحجز مباشرة بدون EdfaPay
        if ($paymentMethod === 'on_arrival') {
            return response()->json([
                'status'         => true,
                'message'        => __('responses.booking_confirmed_pay_on_arrival'),
                'booking_id'     => $booking->id,
                'payment_method' => 'on_arrival',
                'payment_status' => 'unpaid',
                'total_price'    => $totalPrice,
                'redirect_url'   => null,
            ], 201);
        }

        // دفع أونلاين → EdfaPay
        try {
            $paymentResult = app(\App\Services\EdfaPayService::class)->initiatePayment([
                'booking_id'   => $booking->id,
                'booking_type' => get_class($booking),
                'amount'       => $totalPrice,
                'email'        => $request->email,
                'phone'        => $request->phone,
                'first_name'   => $request->name,
                'last_name'    => $request->name,
            ]);

            return response()->json([
                'status'         => true,
                'message'        => __('responses.booking_successful_payment_pending'),
                'booking_id'     => $booking->id,
                'payment_method' => 'online',
                'redirect_url'   => $paymentResult['redirect_url'],
            ]);

        } catch (\Exception $e) {
            // ✅ فشل بدء الدفع → نلغي الحجز فورًا عشان الغرف ترجع للمخزون
            $booking->update(['status' => 'cancelled']);

            return response()->json([
                'status'  => false,
                'message' => __('responses.booking_creation_payment_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /* ============================================================
     |  Update Booking
     * ============================================================ */
    public function update(Request $request, $id)
    {
        $booking = RoomBooking::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'room_id'          => 'exists:rooms,id',
            'start_date'       => 'date|after_or_equal:today',
            'end_date'         => 'date|after:start_date',
            'number_of_rooms'  => 'integer|min:1',
            'number_of_guests' => 'integer|min:1',
            'adults'           => 'integer|min:0',
            'children'         => 'integer|min:0',
            'name'             => 'string',
            'national_id'      => 'required|string',
            'email'            => 'email',
            'phone'            => 'string',
            'room_number'      => 'string',
            'floor_number'     => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking->fill($request->only([
            'room_id', 'start_date', 'end_date', 'number_of_rooms',
            'number_of_guests', 'adults', 'children', 'name', 'national_id',
            'email', 'phone', 'room_number', 'floor_number',
        ]));

        if ($booking->isDirty(['room_id', 'start_date', 'end_date', 'number_of_rooms'])) {

            // ✅ تغيير النوع/التواريخ/العدد لازم يعدي فحص التوافر (مع تجاهل الحجز نفسه)
            $result = DB::transaction(function () use ($booking) {
                $room = Room::whereKey($booking->room_id)->lockForUpdate()->first();

                if (!$room) {
                    return response()->json([
                        'status'  => false,
                        'message' => __('responses.room_not_found'),
                    ], 404);
                }

                if ($booking->isDirty('room_id') && !$room->is_active) {
                    return response()->json([
                        'status'  => false,
                        'message' => __('rooms.type_inactive'),
                    ], 422);
                }

                $start  = Carbon::parse($booking->start_date)->startOfDay();
                $end    = Carbon::parse($booking->end_date)->startOfDay();
                $needed = max(1, (int) $booking->number_of_rooms);

                $available = $this->availability->availableUnits($room, $start, $end, (int) $booking->id);

                if ($available < $needed) {
                    return response()->json([
                        'status'          => false,
                        'code'            => 'ROOM_TYPE_NOT_ENOUGH_UNITS',
                        'message'         => __('rooms.not_enough_units', ['available' => $available]),
                        'available_units' => $available,
                    ], 409);
                }

                $days = $this->availability->nights($start, $end);
                $booking->total_price = $days * $room->price_per_night * $needed;
                $booking->save();

                return true;
            });

            if ($result instanceof JsonResponse) {
                return $result;
            }
        } else {
            $booking->save();
        }

        $room   = Room::with('hotel')->find($booking->room_id);
        $mapUrl = "https://www.google.com/maps?q={$room->hotel->latitude},{$room->hotel->longitude}";

        $qrContent = "🏨 اسم الفندق: {$room->hotel->name}\n"
            . "🌍 الإحداثيات: {$room->hotel->latitude}, {$room->hotel->longitude}\n"
            . "🗺️ رابط الخريطة: {$mapUrl}\n"
            . "👤 الاسم: {$booking->name}\n"
            . "📞 الهاتف: {$booking->phone}\n"
            . "👨👩 البالغون: {$booking->adults}\n"
            . "👦👧 الأطفال: {$booking->children}\n"
            . "👤 رقم الهوية: {$booking->national_id}\n"
            . "🕒 تسجيل الوصول: {$booking->start_date}\n"
            . "🚪 تسجيل المغادرة: {$booking->end_date}\n"
            . "🔐 باسورد الغرفة: {$booking->room_password}\n"
            . "🔐 الباسورد الرئيسي: {$booking->main_password}";

        $fileName = 'qr_' . uniqid() . '.png';
        $path     = 'public/qrcodes/' . $fileName;
        QrCode::format('png')->size(300)->generate($qrContent, storage_path('app/' . $path));

        return response()->json([
            'message' => __('responses.updated_successfully'),
            'ticket'  => [
                'اسم الفندق'         => $room->hotel->name,
                'إحداثيات الفندق'    => "{$room->hotel->latitude}, {$room->hotel->longitude}",
                'رابط الخريطة'       => $mapUrl,
                'اسم'                => $booking->name,
                'رقم الهاتف'         => $booking->phone,
                'تسجيل الوصول'       => $booking->start_date,
                'تسجيل المغادرة'     => $booking->end_date,
                'عدد الضيوف'         => $booking->number_of_guests,
                'رقم الغرفة'         => $booking->room_number,
                'رقم الدور'          => $booking->floor_number,
                'باسورد الغرفة'      => $booking->room_password,
                'الباسورد الرئيسي'   => $booking->main_password,
                'qr_code_url'        => url('storage/qrcodes/' . $fileName),
            ],
        ]);
    }

    /* ============================================================
     |  Pay Booking (Manual)
     * ============================================================ */
    public function payBooking($id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return response()->json(['message' => __('responses.booking_not_found')], 404);
        }

        $booking->status  = 'paid';
        $booking->paid_at = now();
        $booking->save();

        // ✅ إشعار العميل
        $this->safeNotify(function () use ($booking) {
            app(BookingNotificationService::class)->notifyCustomerPaymentConfirmed($booking);
        });

        return response()->json([
            'message' => __('responses.payment_successful'),
            'الحالة'  => $booking->status,
        ]);
    }

    /* ============================================================
     |  Cancel Booking
     * ============================================================ */
  public function cancelBooking()
{
    $user = auth('sanctum')->user();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => __('responses.unauthenticated'),
        ], 401);
    }

    // ✅ نجيب أول حجز "قابل للإلغاء" للمستخدم
    // - pending (أي طريقة دفع)
    // - confirmed + on_arrival (لسه ما دفعش)
    $booking = RoomBooking::where('user_id', $user->id)
        ->where(function ($q) {
            $q->where('status', 'pending')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'confirmed')
                     ->where('payment_method', 'on_arrival')
                     ->whereNull('paid_at');
              });
        })
        ->latest()
        ->first();

    if (!$booking) {
        return response()->json([
            'message' => __('responses.no_pending_booking_to_cancel'),
        ], 404);
    }

    // ✅ لو اتدفع خلاص → ممنوع الإلغاء
    if ($booking->status === 'paid' || $booking->paid_at) {
        return response()->json([
            'message' => __('responses.cannot_cancel_confirmed_booking'),
        ], 403);
    }

    $booking->status = 'cancelled';
    $booking->save();

    // ✅ إشعار صاحب الفندق بالإلغاء
    $this->safeNotify(function () use ($booking) {
        app(BookingNotificationService::class)->notifyOwnerNewRoomBooking($booking);
    });

    return response()->json([
        'message' => __('responses.booking_cancelled_successfully'),
        'status'  => $booking->status,
    ]);
}

    /* ============================================================
     |  Destroy
     * ============================================================ */
    public function destroy($id)
    {
        $booking = RoomBooking::find($id);

        if (!$booking) {
            return response()->json(['error' => __('responses.booking_not_found')], 404);
        }

        $booking->delete();

        return response()->json(['message' => __('responses.booking_cancelled_successfully')]);
    }

    /* ============================================================
     |  Index (My Bookings)
     * ============================================================ */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 422);
        }

        $query = RoomBooking::with('room.hotel');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage  = $request->get('per_page', 5);
        $bookings = $query->latest()->paginate($perPage);

        $bookings->getCollection()->transform(function ($booking) {
            return $this->addQrToBooking($booking);
        });

        return response()->json([
            'status' => true,
            'data'   => $bookings,
        ]);
    }

    /* ============================================================
     |  Get Room Bookings
     * ============================================================ */
    public function getRoomBookings($roomId)
    {
        $room = Room::find($roomId);
        if (!$room) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.room_not_found'),
            ], 404);
        }

        $bookings = RoomBooking::where('room_id', $roomId)
            ->whereIn('status', ['confirmed', 'paid'])
            ->select('id', 'start_date', 'end_date', 'status', 'name')
            ->orderBy('start_date', 'asc')
            ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status'  => true,
                'message' => __('responses.no_bookings_for_room'),
                'data'    => [],
            ]);
        }

        $data = $bookings->map(function ($booking) {
            return [
                'booking_id' => $booking->id,
                'name'       => $booking->name,
                'start_date' => $booking->start_date,
                'end_date'   => $booking->end_date,
                'status'     => $booking->status,
            ];
        });

        return response()->json([
            'status'         => true,
            'room_id'        => $roomId,
            'room_name'      => $room->name ?? 'غير محددة',
            'total_bookings' => $data->count(),
            'bookings'       => $data,
        ]);
    }

    /* ============================================================
     |  Ongoing Bookings (pending + confirmed)
     * ============================================================ */
    public function ongoingBookings()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 401);
        }

        $bookings = RoomBooking::with('room.hotel')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($booking) {
                return $this->addQrToBooking($booking);
            });

        return response()->json([
            'status'  => true,
            'message' => __('responses.ongoing_bookings_retrieved'),
            'data'    => $bookings,
        ]);
    }

    /* ============================================================
     |  Completed Bookings (paid)
     * ============================================================ */
    public function completedBookings()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 401);
        }

        $bookings = RoomBooking::with('room.hotel')
            ->where('status', 'paid')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($booking) {
                return $this->addQrToBooking($booking);
            });

        return response()->json([
            'status'  => true,
            'message' => __('responses.completed_bookings_retrieved'),
            'data'    => $bookings,
        ]);
    }

    /* ============================================================
     |  Cancelled Bookings
     * ============================================================ */
    public function cancelledBookings()
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 401);
        }

        $bookings = RoomBooking::with('room.hotel')
            ->where('status', 'cancelled')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($booking) {
                return $this->addQrToBooking($booking);
            });

        return response()->json([
            'status'  => true,
            'message' => __('responses.cancelled_bookings_retrieved'),
            'data'    => $bookings,
        ]);
    }

    /* ============================================================
     |  Helper: Add QR to booking
     * ============================================================ */
    private function addQrToBooking($booking)
    {
        $room  = $booking->room;
        $hotel = $room->hotel ?? null;

        if (!$hotel) {
            return $booking;
        }

        $mapUrl = "https://www.google.com/maps?q={$hotel->latitude},{$hotel->longitude}";

        $qrContent = "🏨 اسم الفندق: {$hotel->name}\n"
            . "🌍 الإحداثيات: {$hotel->latitude}, {$hotel->longitude}\n"
            . "🗺️ رابط الخريطة: {$mapUrl}\n"
            . "👤 الاسم: {$booking->name}\n"
            . "📞 الهاتف: {$booking->phone}\n"
            . "🛏️ رقم الغرفة: {$booking->room_number}\n"
            . "👤 رقم الهوية: {$booking->national_id}\n"
            . "📶 رقم الدور: {$booking->floor_number}\n"
            . "👨👩 البالغون: {$booking->adults}\n"
            . "👦👧 الأطفال: {$booking->children}\n"
            . "🕒 تسجيل الوصول: {$booking->start_date}\n"
            . "🚪 تسجيل المغادرة: {$booking->end_date}\n"
            . "🔐 باسورد الغرفة: {$booking->room_password}\n"
            . "🔐 الباسورد الرئيسي: {$booking->main_password}";

        $fileName = 'qr_' . uniqid() . '.png';
        $path     = 'public/qrcodes/' . $fileName;

        QrCode::format('png')->size(300)->encoding('UTF-8')->generate($qrContent, storage_path('app/' . $path));

        $bookingData = $booking->toArray();
        $bookingData['qr_code_url'] = url('storage/qrcodes/' . $fileName);
        $bookingData['qr_content']  = $qrContent;

        return $bookingData;
    }

    /* ============================================================
     |  Update Booking Status (hotel owner / admin)
     * ============================================================ */
    public function updateBookingStatus(Request $request, $id)
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = RoomBooking::with('room.hotel')->find($id);

        if (!$booking) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.booking_not_found'),
            ], 404);
        }

        $hotelOwnerId = $booking->room?->hotel?->user_id;

        if ($user->role !== 'admin' && $user->id !== $hotelOwnerId) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthorized_booking_status_update'),
            ], 403);
        }

        $booking->status = $request->status;
        $booking->save();

        return response()->json([
            'status'  => true,
            'message' => __('responses.status_updated_successfully'),
            'data'    => [
                'booking_id' => $booking->id,
                'new_status' => $booking->status,
                'hotel_name' => $booking->room?->hotel?->name ?? 'غير محدد',
            ],
        ]);
    }

    /* ============================================================
     |  Mark as Paid (for on_arrival bookings)
     * ============================================================ */
    public function markAsPaid(Request $request, $id)
    {
        $user = auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthenticated'),
            ], 401);
        }

        $booking = RoomBooking::with('room.hotel')->find($id);

        if (!$booking) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.booking_not_found'),
            ], 404);
        }

        $hotelOwnerId = $booking->room?->hotel?->user_id;

        if ($user->role !== 'admin' && $user->id !== $hotelOwnerId) {
            return response()->json([
                'status'  => false,
                'message' => __('responses.unauthorized_booking_status_update'),
            ], 403);
        }

        if ($booking->payment_method !== 'on_arrival') {
            return response()->json([
                'status'  => false,
                'message' => __('responses.only_on_arrival_bookings_can_be_marked'),
            ], 422);
        }

        if ($booking->status === 'paid') {
            return response()->json([
                'status'  => false,
                'message' => __('responses.booking_already_paid'),
            ], 422);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'status'  => false,
                'message' => __('responses.cannot_pay_cancelled_booking'),
            ], 422);
        }

        $booking->status  = 'paid';
        $booking->paid_at = now();
        $booking->save();

        // ✅ إشعار العميل بتأكيد الدفع
        $this->safeNotify(function () use ($booking) {
            app(BookingNotificationService::class)->notifyCustomerPaymentConfirmed($booking);
        });

        $bookingWithQr = $this->addQrToBooking($booking->fresh()->load('room.hotel'));

        return response()->json([
            'status'  => true,
            'message' => __('responses.booking_marked_as_paid'),
            'data'    => $bookingWithQr,
        ]);
    }
}

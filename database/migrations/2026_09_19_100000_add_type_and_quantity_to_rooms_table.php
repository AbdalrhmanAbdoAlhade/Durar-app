<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المرحلة 1 - الخطوة 1:
 * جدول rooms بقى بيمثل "نوع غرفة" (Room Type) وليس غرفة فعلية واحدة.
 *   room_type  : تصنيف النوع (single / double / suite ...)
 *   quantity   : عدد الغرف الفعلية من النوع ده في الفندق
 *   max_guests : أقصى عدد ضيوف للغرفة الواحدة (اختياري)
 *   is_active  : إخفاء/إظهار النوع من غير حذفه
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type', 30)->default('standard')->after('name');
            $table->unsignedInteger('quantity')->default(1)->after('room_type');
            $table->unsignedSmallInteger('max_guests')->nullable()->after('quantity');
            $table->boolean('is_active')->default(true)->after('max_guests');

            $table->index(['hotel_id', 'is_active'], 'rooms_hotel_active_idx');
        });

        // إندكس لسرعة حساب التوافر (بيتنادى في كل حجز وكل عرض للفندق)
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->index(['room_id', 'start_date', 'end_date', 'status'], 'rb_availability_idx');
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex('rb_availability_idx');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_hotel_active_idx');
            $table->dropColumn(['room_type', 'quantity', 'max_guests', 'is_active']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * المرحلة 1 - الخطوة 2 (SQL مباشر، مش Seeder):
 * الغرف القديمة كانت صف لكل غرفة فعلية. هنا بنجمع المتشابه (نفس الفندق + نفس الاسم + نفس السعر + نفس المساحة)
 * في صف واحد وبنحط عددهم في quantity، وبنحوّل room_id في room_bookings لصف النوع اللي فضل.
 *
 * ⚠️ خد Backup للداتابيز قبل التشغيل — الدمج مش بيترجع (down فاضية).
 * ⚠️ room_bookings.room_id مفيهوش Foreign Key فالحذف مش هيعمل cascade، بس لو في جداول تانية عندك
 *    بتشاور على rooms.id (مثلاً service requests) لازم تضيف لها UPDATE زي بتاع room_bookings تحت.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS room_merge_map');

        // خريطة: كل غرفة قديمة -> الصف اللي هيمثل النوع (أقل id في المجموعة) + عدد المجموعة
        DB::statement("
            CREATE TEMPORARY TABLE room_merge_map AS
            SELECT r.id AS old_id, g.keeper_id, g.qty
            FROM rooms r
            JOIN (
                SELECT MIN(id)  AS keeper_id,
                       COUNT(*) AS qty,
                       hotel_id,
                       CAST(name AS CHAR)   AS name_key,
                       price_per_night,
                       COALESCE(size, '')   AS size_key
                FROM rooms
                GROUP BY hotel_id, CAST(name AS CHAR), price_per_night, COALESCE(size, '')
            ) g
              ON  g.hotel_id        = r.hotel_id
              AND g.name_key        = CAST(r.name AS CHAR)
              AND g.price_per_night = r.price_per_night
              AND g.size_key        = COALESCE(r.size, '')
        ");

        $before = DB::table('rooms')->count();

        DB::transaction(function () {
            // 1) الكمية + مسح رقم الغرفة/الدور لو النوع بقى بيمثل أكتر من غرفة
            DB::statement("
                UPDATE rooms r
                JOIN (SELECT DISTINCT keeper_id, qty FROM room_merge_map) m ON m.keeper_id = r.id
                SET r.quantity     = m.qty,
                    r.room_number  = IF(m.qty > 1, NULL, r.room_number),
                    r.floor_number = IF(m.qty > 1, NULL, r.floor_number)
            ");

            // 2) الحجوزات القديمة تشاور على صف النوع (رقم الغرفة القديم محفوظ جوه الحجز نفسه)
            DB::statement("
                UPDATE room_bookings rb
                JOIN room_merge_map m ON m.old_id = rb.room_id
                SET rb.room_id = m.keeper_id
                WHERE m.old_id <> m.keeper_id
            ");

            // 3) حذف الصفوف المكررة
            DB::statement("
                DELETE r FROM rooms r
                JOIN room_merge_map m ON m.old_id = r.id
                WHERE m.old_id <> m.keeper_id
            ");

            // 4) تخمين بسيط للتصنيف من الاسم (صاحب الفندق يقدر يعدله بعدين)
            DB::statement("
                UPDATE rooms
                SET room_type = CASE
                    WHEN CAST(name AS CHAR) LIKE '%suite%'   OR CAST(name AS CHAR) LIKE '%جناح%'   THEN 'suite'
                    WHEN CAST(name AS CHAR) LIKE '%twin%'                                            THEN 'twin'
                    WHEN CAST(name AS CHAR) LIKE '%double%'  OR CAST(name AS CHAR) LIKE '%مزدوج%'
                      OR CAST(name AS CHAR) LIKE '%ثنائي%'                                           THEN 'double'
                    WHEN CAST(name AS CHAR) LIKE '%single%'  OR CAST(name AS CHAR) LIKE '%فردي%'   THEN 'single'
                    ELSE room_type
                END
            ");
        });

        $after = DB::table('rooms')->count();
        Log::info("merge_duplicate_rooms: rooms rows {$before} -> {$after}");

        DB::statement('DROP TEMPORARY TABLE IF EXISTS room_merge_map');
    }

    public function down(): void
    {
        // الدمج مش قابل للرجوع — استرجع من الـ Backup لو محتاج.
    }
};

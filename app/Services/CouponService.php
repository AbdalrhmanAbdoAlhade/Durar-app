<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate a coupon for the given categories and (optionally) a specific user.
     * Checks: exists, active, date range, global usage_limit, usage_limit_per_user.
     */
    public function validateForCategories(
        string $code,
        array $categoryIds,
        ?int $userId = null
    ): ?Coupon {
        $coupon = Coupon::where('code', $code)
            ->with('categories')
            ->first();

        if (! $coupon || ! $coupon->isValid()) {
            return null;
        }

        // لازم ينطبق على قسم واحد على الأقل من السلة
        $couponCategoryIds = $coupon->categories->pluck('id')->all();

        if (empty(array_intersect($categoryIds, $couponCategoryIds))) {
            return null;
        }

        // حد الاستخدام لكل مستخدم
        if ($userId && $coupon->usage_limit_per_user) {
            $userUsage = (int) DB::table('coupon_user')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->value('used_count');

            if ($userUsage >= $coupon->usage_limit_per_user) {
                return null;
            }
        }

        return $coupon;
    }

    /**
     * سجل استخدام الكوبون للمستخدم (بعد إنشاء الطلب بنجاح).
     */
    public function recordUsage(Coupon $coupon, int $userId): void
    {
        $coupon->increment('used_count');

        $existing = DB::table('coupon_user')
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('coupon_user')
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->update([
                    'used_count' => $existing->used_count + 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('coupon_user')->insert([
                'coupon_id'  => $coupon->id,
                'user_id'    => $userId,
                'used_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
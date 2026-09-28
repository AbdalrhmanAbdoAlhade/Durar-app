<?php

namespace App\Services;

use App\Models\Coupon;

class CouponService
{
    /**
     * Find a usable coupon by code that applies to at least one of the given
     * category ids. Returns null when the coupon is missing, invalid
     * (inactive / out of date range / usage limit reached) or scoped to
     * other categories.
     */
    public function validateForCategories(string $code, array $categoryIds): ?Coupon
    {
        $coupon = Coupon::where('code', $code)->with('categories')->first();

        if (! $coupon || ! $coupon->isValid()) {
            return null;
        }

        $couponCategoryIds = $coupon->categories->pluck('id')->all();

        if (empty(array_intersect($categoryIds, $couponCategoryIds))) {
            return null;
        }

        return $coupon;
    }
}

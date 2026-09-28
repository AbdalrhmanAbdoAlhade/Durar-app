<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected CouponService $coupons)
    {
    }

    // POST /api/coupons/check (public)
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $coupon = $this->coupons->validateForCategories(
            $data['code'],
            $data['category_ids'] ?? []
        );

        if (! $coupon) {
            return $this->error('Coupon is invalid or does not apply', 422);
        }

        return $this->success(new CouponResource($coupon->load('categories')), 'Coupon is valid');
    }

    // GET /api/admin/coupons (admin)
    public function index(): JsonResponse
    {
        $coupons = Coupon::with('categories')->latest()->paginate(20);

        return $this->success(CouponResource::collection($coupons));
    }

    // GET /api/admin/coupons/{coupon} (admin)
    public function show(Coupon $coupon): JsonResponse
    {
        return $this->success(new CouponResource($coupon->load('categories')));
    }

    // POST /api/admin/coupons (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $coupon = Coupon::create($data);
        $coupon->categories()->sync($categoryIds);

        return $this->success(new CouponResource($coupon->load('categories')), 'Coupon created', 201);
    }

    // PUT /api/admin/coupons/{coupon} (admin)
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:coupons,code,'.$coupon->id],
            'type' => ['sometimes', 'required', 'in:percentage,fixed'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        if (array_key_exists('category_ids', $data)) {
            $coupon->categories()->sync($data['category_ids'] ?? []);
            unset($data['category_ids']);
        }

        $coupon->update($data);

        return $this->success(new CouponResource($coupon->fresh('categories')), 'Coupon updated');
    }

    // DELETE /api/admin/coupons/{coupon} (admin)
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return $this->success(null, 'Coupon deleted');
    }
}

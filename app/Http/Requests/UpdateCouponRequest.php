<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:coupons,code,'.$this->coupon?->id],
            'type' => ['sometimes', 'required', 'in:percentage,fixed'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
        ];
    }
}

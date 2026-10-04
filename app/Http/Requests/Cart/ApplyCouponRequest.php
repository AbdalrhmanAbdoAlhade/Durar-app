<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string', 'max:50'],
            'session_id' => ['required_without:user', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('Coupon code is required'),
            'session_id.required_without' => __('Session ID is required for guest users'),
        ];
    }
}

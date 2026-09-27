<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:255'],
            'shipping_address.phone' => ['required', 'string', 'max:30'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.address_line' => ['required', 'string', 'max:500'],
            'shipping_address.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

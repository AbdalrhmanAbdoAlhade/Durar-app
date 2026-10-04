<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
{
    return [
        'product_id' => ['required', 'integer', 'exists:products,id'],
        'quantity'   => ['required', 'integer', 'min:1'],
        'session_id' => ['nullable', 'string', 'max:100'], // اختياري دلوقتي
    ];
}

    public function messages(): array
    {
        return [
            'product_id.required' => __('Product is required'),
            'product_id.exists'   => __('Product not found'),
            'quantity.min'        => __('Quantity must be at least 1'),
            'session_id.required_without' => __('Session ID is required for guest users'),
        ];
    }
}

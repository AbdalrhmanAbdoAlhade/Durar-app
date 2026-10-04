<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity'   => ['required', 'integer', 'min:0'],
            'session_id' => ['required_without:user', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => __('Quantity cannot be negative'),
            'session_id.required_without' => __('Session ID is required for guest users'),
        ];
    }
}

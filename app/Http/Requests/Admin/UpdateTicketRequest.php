<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar'         => ['sometimes', 'required', 'string', 'max:255'],
            'name_en'         => ['sometimes', 'required', 'string', 'max:255'],
            'details_ar'      => ['nullable', 'string'],
            'details_en'      => ['nullable', 'string'],
            'price'           => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'event_start'     => ['sometimes', 'required', 'date'],
            'event_end'       => ['sometimes', 'required', 'date', 'after:event_start'],
            'location'        => ['nullable', 'string', 'max:255'],
            'quantity'        => ['nullable', 'integer', 'min:-1'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }
}
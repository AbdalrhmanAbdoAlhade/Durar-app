<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar'         => ['required', 'string', 'max:255'],
            'name_en'         => ['required', 'string', 'max:255'],
            'details_ar'      => ['nullable', 'string'],
            'details_en'      => ['nullable', 'string'],
            'price'           => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'event_start'     => ['required', 'date', 'after:now'],
            'event_end'       => ['required', 'date', 'after:event_start'],
            'location'        => ['nullable', 'string', 'max:255'],
            'quantity'        => ['nullable', 'integer', 'min:-1'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required'     => 'اسم التذكرة بالعربي مطلوب',
            'name_en.required'     => 'اسم التذكرة بالإنجليزي مطلوب',
            'price.required'       => 'السعر مطلوب',
            'event_start.required' => 'وقت بداية الحدث مطلوب',
            'event_start.after'    => 'وقت البداية يجب أن يكون في المستقبل',
            'event_end.required'   => 'وقت نهاية الحدث مطلوب',
            'event_end.after'      => 'وقت النهاية يجب أن يكون بعد وقت البداية',
        ];
    }
}
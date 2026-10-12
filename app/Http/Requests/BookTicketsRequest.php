<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guest مسموح
    }

    public function rules(): array
    {
        return [
            'customer_name'           => ['required', 'string', 'max:150'],
            'national_id'             => ['required', 'string', 'max:20'],
            'phone'                   => ['required', 'string', 'max:20'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.ticket_id'       => ['required', 'integer', 'exists:tickets,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'الاسم مطلوب',
            'national_id.required'   => 'رقم الهوية مطلوب',
            'phone.required'         => 'رقم الهاتف مطلوب',
            'items.required'         => 'يجب اختيار تذكرة واحدة على الأقل',
        ];
    }
}
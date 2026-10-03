<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'cover_image' => ['required', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_rare' => ['nullable', 'boolean'],

            // ===== الحقول الجديدة =====
            'classification' => ['nullable', 'string', 'max:255'],
            'hardness' => ['nullable', 'string', 'max:50'],
            'origin_country' => ['nullable', 'string', 'size:2'],
            'origin_details' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', 'string', 'max:20'],
        ];
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // استخراج الـ ID سواء كان Model أو String
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'name_ar'    => ['sometimes', 'required', 'string', 'max:255'],
            'name_en'    => ['sometimes', 'required', 'string', 'max:255'],
            'slug'       => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($categoryId)],
            'image'      => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
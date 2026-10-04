<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => __('Product is required'),
            'product_id.exists'   => __('Product not found'),
            'rating.required'     => __('Rating is required'),
            'rating.min'          => __('Rating must be at least 1'),
            'rating.max'          => __('Rating cannot exceed 5'),
            'comment.max'         => __('Comment is too long'),
        ];
    }
}

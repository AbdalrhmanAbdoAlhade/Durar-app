<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'   => __('This email is already taken'),
            'avatar.image'   => __('The file must be an image'),
            'avatar.mimes'   => __('Allowed image types: jpeg, png, jpg, gif, webp'),
            'avatar.max'     => __('Image size must not exceed 5MB'),
        ];
    }
}
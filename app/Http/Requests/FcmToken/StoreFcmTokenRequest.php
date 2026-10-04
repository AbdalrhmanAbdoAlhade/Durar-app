<?php

namespace App\Http\Requests\FcmToken;

use Illuminate\Foundation\Http\FormRequest;

class StoreFcmTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'token'    => ['required', 'string', 'max:500'],
            'device'   => ['nullable', 'string', 'max:100'], // e.g. android, ios, web
            'platform' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => __('FCM token is required'),
        ];
    }
}

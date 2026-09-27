<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,paid,processing,shipped,delivered,cancelled,refunded'],
        ];
    }
}

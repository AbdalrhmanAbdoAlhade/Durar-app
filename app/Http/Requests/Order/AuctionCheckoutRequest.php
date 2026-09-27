<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AuctionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'auction_id'       => ['required', 'integer', 'exists:auctions,id'],
            'shipping_name'    => ['required', 'string', 'max:255'],
            'shipping_phone'   => ['required', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_city'    => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'auction_id.required'       => __('Auction is required'),
            'auction_id.exists'         => __('Auction not found'),
            'shipping_name.required'    => __('Shipping name is required'),
            'shipping_phone.required'   => __('Shipping phone is required'),
            'shipping_address.required' => __('Shipping address is required'),
        ];
    }
}

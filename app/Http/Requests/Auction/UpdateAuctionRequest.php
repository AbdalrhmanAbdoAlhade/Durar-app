<?php

namespace App\Http\Requests\Auction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('auctions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name_ar'           => ['sometimes', 'string', 'max:255'],
            'name_en'           => ['sometimes', 'string', 'max:255'],
            'description_ar'    => ['nullable', 'string'],
            'description_en'    => ['nullable', 'string'],
            'starting_price'    => ['sometimes', 'numeric', 'min:0'],
            'min_bid_increment' => ['nullable', 'numeric', 'min:0.01'],
            'reserve_price'     => ['nullable', 'numeric', 'min:0'],
            'starts_at'         => ['sometimes', 'date'],
            'ends_at'           => ['sometimes', 'date', 'after:starts_at'],
            'status'            => ['nullable', 'in:pending,active,ended,cancelled'],
            'cover_image'       => ['nullable', 'string', 'max:500'],
            'images'            => ['nullable', 'array'],
            'images.*'          => ['string', 'max:500'],
        ];
    }
}

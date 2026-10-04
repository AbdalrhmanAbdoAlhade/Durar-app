<?php

namespace App\Http\Requests\Auction;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('auctions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name_ar'           => ['required', 'string', 'max:255'],
            'name_en'           => ['required', 'string', 'max:255'],
            'description_ar'    => ['nullable', 'string'],
            'description_en'    => ['nullable', 'string'],
            'starting_price'    => ['required', 'numeric', 'min:0'],
            'min_bid_increment' => ['nullable', 'numeric', 'min:0.01'],
            'reserve_price'     => ['nullable', 'numeric', 'min:0'],
            'starts_at'         => ['required', 'date', 'after_or_equal:now'],
            'ends_at'           => ['required', 'date', 'after:starts_at'],
            'status'            => ['nullable', 'in:pending,active,ended,cancelled'],
            'cover_image'       => ['nullable', 'string', 'max:500'],
            'images'            => ['nullable', 'array'],
            'images.*'          => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required'        => __('Arabic name is required'),
            'name_en.required'        => __('English name is required'),
            'starting_price.required' => __('Starting price is required'),
            'starts_at.after_or_equal'=> __('Start time must be in the future'),
            'ends_at.after'           => __('End time must be after start time'),
        ];
    }
}

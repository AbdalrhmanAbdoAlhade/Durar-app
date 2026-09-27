<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'cover_image' => $this->cover_image,
            'gallery' => ProductImageResource::collection($this->whenLoaded('images')),
            'metadata' => $this->metadata,
            'starting_price' => (float) $this->starting_price,
            'current_price' => (float) $this->current_price,
            'min_bid_increment' => (float) $this->min_bid_increment,
            'minimum_next_bid' => $this->minimumNextBid(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'is_biddable' => $this->isBiddable(),
            'bids_count' => $this->whenCounted('bids'),
            'bids' => AuctionBidResource::collection($this->whenLoaded('bids')),
            'winner' => $this->when($this->winner_id, fn () => [
                'id' => $this->winner?->id,
                'name' => $this->winner?->name,
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionBidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->whenLoaded('user', fn () => $this->user->id),
                'name' => $this->whenLoaded('user', fn () => $this->user->name),
            ],
            'amount' => (float) $this->amount,
            'created_at' => $this->created_at,
        ];
    }
}

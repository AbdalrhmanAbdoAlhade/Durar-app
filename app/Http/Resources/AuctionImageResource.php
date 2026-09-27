<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'path'       => $this->path,
            'sort_order' => $this->sort_order,
            'url'        => $this->path ? asset('storage/' . $this->path) : null,
        ];
    }
}

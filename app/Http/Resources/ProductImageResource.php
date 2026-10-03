<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image' => $this->image,
            'sort_order' => $this->sort_order,
            'url' => $this->image ? asset('storage'.$this->image) : null,
        ];
    }
}

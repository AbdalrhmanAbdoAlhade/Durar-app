<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'cover_image' => $this->cover_image,
            'gallery' => ProductImageResource::collection($this->whenLoaded('images')),
            'price' => (float) $this->price,
            'discount_percentage' => $this->discount_percentage,
            'final_price' => $this->final_price,
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
        ];
    }
}

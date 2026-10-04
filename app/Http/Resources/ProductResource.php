<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    use ResolvesLocale;

    public function toArray(Request $request): array
    {
        $base = [
            'id'                  => $this->id,
            'category_id'         => $this->category_id,
            'category'            => new CategoryResource($this->whenLoaded('category')),
            'slug'                => $this->slug,
            'cover_image'         => $this->cover_image,
            'gallery'             => ProductImageResource::collection($this->whenLoaded('images')),
            'price'               => (float) $this->price,
            'discount_percentage' => $this->discount_percentage,
            'final_price'         => $this->final_price,
            'quantity'            => $this->quantity,
            'is_active'           => $this->is_active,
            'is_rare'             => (bool) $this->is_rare,
            'sales_count'         => (int) $this->sales_count,
            // حقول إضافية إن وُجدت
            'classification'      => $this->classification ?? null,
            'hardness'            => $this->hardness ?? null,
            'origin_country'      => $this->origin_country ?? null,
            'origin_details'      => $this->origin_details ?? null,
            'weight'              => $this->weight ?? null,
            'weight_unit'         => $this->weight_unit ?? null,
            'qr_code'             => $this->qr_code ?? null,
        ];

        if ($this->isLocaleForced($request)) {
            return array_merge($base, [
                'name'        => $this->localized($request, 'name'),
                'description' => $this->localized($request, 'description'),
            ]);
        }

        return array_merge($base, [
            'name_ar'         => $this->name_ar,
            'name_en'         => $this->name_en,
            'description_ar'  => $this->description_ar,
            'description_en'  => $this->description_en,
        ]);
    }
}
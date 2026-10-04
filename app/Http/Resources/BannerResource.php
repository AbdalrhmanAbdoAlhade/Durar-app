<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    use ResolvesLocale;

    public function toArray(Request $request): array
    {
        $base = [
            'id'              => $this->id,
            'type'            => $this->type,
            'image'           => $this->image,
            'bannerable_id'   => $this->bannerable_id,
            'bannerable_type' => $this->bannerable_type,
            'coupon_id'       => $this->coupon_id,
            'coupon'          => new CouponResource($this->whenLoaded('coupon')),
            'sort_order'      => $this->sort_order,
            'is_active'       => $this->is_active,
        ];

        if ($this->isLocaleForced($request)) {
            return array_merge($base, [
                'title' => $this->localized($request, 'title'),
            ]);
        }

        return array_merge($base, [
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
        ]);
    }
}
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id'   => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'product' => $this->whenLoaded('product', function () use ($request) {
                $product = [
                    'id'          => $this->product->id,
                    'cover_image' => $this->product->cover_image,
                ];

                // لو فيه Accept-Language → رجع name واحد حسب اللغة
                if ($request->attributes->get('locale_forced')) {
                    $locale = $request->attributes->get('api_locale', 'ar');
                    $product['name'] = $locale === 'en'
                        ? $this->product->name_en
                        : $this->product->name_ar;
                } else {
                    // مفيش هيدر → رجع الاتنين
                    $product['name_ar'] = $this->product->name_ar;
                    $product['name_en'] = $this->product->name_en;
                }

                return $product;
            }),

            'rating'      => $this->rating,
            'comment'     => $this->comment,
            'is_approved' => $this->is_approved,
            'created_at'  => $this->created_at,
        ];
    }
}
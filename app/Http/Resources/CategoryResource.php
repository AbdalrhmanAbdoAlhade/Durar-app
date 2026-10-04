<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    use ResolvesLocale;

    public function toArray(Request $request): array
    {
        if ($this->isLocaleForced($request)) {
            return [
                'id'         => $this->id,
                'name'       => $this->localized($request, 'name'),
                'slug'       => $this->slug,
                'image'      => $this->image,
                'sort_order' => $this->sort_order,
                'is_active'  => $this->is_active,
            ];
        }

        // مفيش Accept-Language → كل الحقول
        return [
            'id'         => $this->id,
            'name_ar'    => $this->name_ar,
            'name_en'    => $this->name_en,
            'slug'       => $this->slug,
            'image'      => $this->image,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ];
    }
}
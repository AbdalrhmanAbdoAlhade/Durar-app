<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title_ar'     => $this->title_ar,
            'title_en'     => $this->title_en,
            'slug'         => $this->slug,
            'excerpt_ar'   => $this->excerpt_ar,
            'excerpt_en'   => $this->excerpt_en,
            'content_ar'   => $this->content_ar,
            'content_en'   => $this->content_en,
            'cover_image'  => $this->cover_image,
            'cover_url'    => $this->cover_image ? asset('storage/' . ltrim($this->cover_image, '/')) : null,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at,
            'product_id'   => $this->product_id,
            'product'      => $this->whenLoaded('product', fn () => $this->product ? new ProductResource($this->product) : null),
            'images'       => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id'         => $img->id,
                'image'      => $img->image,
                'sort_order' => $img->sort_order,
                'url'        => asset('storage/' . ltrim($img->image, '/')),
            ])),
            'products'     => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
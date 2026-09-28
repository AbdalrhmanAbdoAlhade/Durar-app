<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProductService
{
    public function listActive(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->with(['category', 'images'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($q2) => $q2->where('name_ar', 'like', $term)->orWhere('name_en', 'like', $term));
            })
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', $request->float('max_price')))
            ->latest()
            ->paginate($perPage);
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'images'])
            ->latest()
            ->paginate($perPage);
    }

    public function find(int $id): Product
    {
        return Product::with(['category', 'images', 'approvedReviews.user'])->findOrFail($id);
    }

    public function create(array $data): Product
    {
        $data['slug'] = $this->uniqueSlug($data['name_en']);

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if (isset($data['name_en']) && $data['name_en'] !== $product->name_en) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $product->id);
        }

        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function addGalleryImages(Product $product, array $paths): void
    {
        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        foreach ($paths as $i => $path) {
            $product->images()->create([
                'image' => $path,
                'sort_order' => $nextOrder + $i,
            ]);
        }
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

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
        $query = Product::query()
            ->active()
            ->with(['category', 'images'])
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($q2) => $q2
                    ->where('name_ar', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    // ===== البحث في الحقول الجديدة =====
                    ->orWhere('classification', 'like', $term)
                    ->orWhere('hardness', 'like', $term)
                    ->orWhere('origin_details', 'like', $term)
                );
            })
            ->when($request->filled('min_price'), fn ($q) => $q->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', $request->float('max_price')))

            // ===== فلترة الحقول الجديدة =====
            ->when($request->filled('classification'), fn ($q) => $q->where('classification', 'like', '%'.$request->string('classification').'%'))
            ->when($request->filled('hardness'), fn ($q) => $q->where('hardness', 'like', '%'.$request->string('hardness').'%'))
            ->when($request->filled('origin_details'), fn ($q) => $q->where('origin_details', 'like', '%'.$request->string('origin_details').'%'))
            ->when($request->filled('origin_country'), fn ($q) => $q->where('origin_country', $request->string('origin_country')))
            ->when($request->filled('weight_unit'), fn ($q) => $q->where('weight_unit', $request->string('weight_unit')));

        // الفلاتر الخاصة باللاندينج بيدج
        match ($request->string('filter')->toString()) {
            'best_selling' => $query->where('sales_count', '>', 0)
                                    ->orderByDesc('sales_count')
                                    ->orderByDesc('id'),
            'rare'         => $query->where('is_rare', true)->latest(),
            'newest'       => $query->latest(),
            default        => $query->latest(),
        };

        return $query->paginate($perPage);
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
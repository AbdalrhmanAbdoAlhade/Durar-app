<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponseTrait;

    // GET /api/products (public)
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->active()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->with(['category', 'images'])
            ->latest()
            ->paginate(20);

        return $this->success(ProductResource::collection($products));
    }

    // GET /api/products/{product} (public)
    public function show(Product $product): JsonResponse
    {
        return $this->success(new ProductResource($product->load(['category', 'images'])));
    }

    // GET /api/admin/products (admin)
    public function adminIndex(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->with(['category', 'images'])
            ->latest()
            ->paginate(20);

        return $this->success(ProductResource::collection($products));
    }

    // POST /api/admin/products (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'quantity' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        unset($data['gallery']);
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name_en']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('products', 'public');
        }

        $product = Product::create($data);

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $i => $file) {
                $product->images()->create([
                    'image' => $file->store('products', 'public'),
                    'sort_order' => $i,
                ]);
            }
        }

        return $this->success(new ProductResource($product->load(['category', 'images'])), 'Product created', 201);
    }

    // PUT /api/admin/products/{product} (admin)
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug,'.$product->id],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:4096'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        unset($data['gallery']);

        if (isset($data['name_en']) && $data['name_en'] !== $product->name_en && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $product->id);
        }

        if ($request->hasFile('cover_image')) {
            if ($product->cover_image) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('products', 'public');
        }

        $product->update($data);

        if ($request->hasFile('gallery')) {
            $nextOrder = (int) $product->images()->max('sort_order') + 1;
            foreach ($request->file('gallery') as $i => $file) {
                $product->images()->create([
                    'image' => $file->store('products', 'public'),
                    'sort_order' => $nextOrder + $i,
                ]);
            }
        }

        return $this->success(new ProductResource($product->fresh(['category', 'images'])), 'Product updated');
    }

    // DELETE /api/admin/products/{product} (admin)
    public function destroy(Product $product): JsonResponse
    {
        if ($product->cover_image) {
            Storage::disk('public')->delete($product->cover_image);
        }

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image);
        }

        $product->delete();

        return $this->success(null, 'Product deleted');
    }

    // DELETE /api/admin/products/{product}/images/{image} (admin)
    public function destroyImage(Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            return $this->error('Not found', 404);
        }

        Storage::disk('public')->delete($image->image);
        $image->delete();

        return $this->success(null, 'Product image deleted');
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

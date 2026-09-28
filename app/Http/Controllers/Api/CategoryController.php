<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    // GET /api/categories (public)
    public function index(): JsonResponse
    {
        $categories = Category::active()->orderBy('sort_order')->get();

        return $this->success(CategoryResource::collection($categories));
    }

    // GET /api/categories/{category} (public)
    public function show(Category $category): JsonResponse
    {
        return $this->success(new CategoryResource($category));
    }

    // GET /api/admin/categories (admin)
    public function adminIndex(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')->latest()->paginate(20);

        return $this->success(CategoryResource::collection($categories));
    }

    // POST /api/admin/categories (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'image' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['name_en']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return $this->success(new CategoryResource($category), 'Category created', 201);
    }

    // PUT /api/admin/categories/{category} (admin)
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug,'.$category->id],
            'image' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (isset($data['name_en']) && $data['name_en'] !== $category->name_en && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $category->id);
        }

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return $this->success(new CategoryResource($category->fresh()), 'Category updated');
    }

    // DELETE /api/admin/categories/{category} (admin)
    public function destroy(Category $category): JsonResponse
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return $this->success(null, 'Category deleted');
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponseTrait, ImageConverterTrait;

    // GET /api/categories (public)
    public function index(): JsonResponse
    {
        $categories = Category::active()
            ->orderBy('sort_order')
            ->get();

        return $this->success(
            CategoryResource::collection($categories)
        );
    }

    // GET /api/categories/{category} (public)
    public function show(Category $category): JsonResponse
    {
        return $this->success(
            new CategoryResource($category)
        );
    }

    // GET /api/admin/categories (admin)
    public function adminIndex(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('sort_order')
            ->latest()
            ->paginate(20);

        return $this->success(
            CategoryResource::collection($categories)
        );
    }

    // POST /api/admin/categories (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => [
                'required',
                'string',
                'max:255',
            ],

            'name_en' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:255',
                'unique:categories,slug',
            ],

            'image' => [
                'nullable',
                'image',
                'max:4096',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Slug
        |--------------------------------------------------------------------------
        */

        $data['slug'] = $data['slug']
            ?? $this->uniqueSlug($data['name_en']);

        /*
        |--------------------------------------------------------------------------
        | Store Image as WebP
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImageAsWebp(
                $request->file('image'),
                'categories'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Category
        |--------------------------------------------------------------------------
        */

        $category = Category::create($data);

        return $this->success(
            new CategoryResource($category),
            'Category created successfully',
            201
        );
    }

    // PUT /api/admin/categories/{category} (admin)
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Remove Image From Mass Assignment
        |--------------------------------------------------------------------------
        */

        unset($data['image']);

        /*
        |--------------------------------------------------------------------------
        | Generate New Slug If Name Changed
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['name_en']) &&
            $data['name_en'] !== $category->name_en &&
            empty($data['slug'] ?? null)
        ) {
            $data['slug'] = $this->uniqueSlug(
                $data['name_en'],
                $category->id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Replace Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {

            // Delete old image
            if (
                !empty($category->image) &&
                $category->image !== '0'
            ) {
                $oldImage = ltrim(
                    $category->image,
                    '/'
                );

                if (Storage::disk('public')->exists($oldImage)) {
                    Storage::disk('public')->delete($oldImage);
                }
            }

            // Store new image as WebP
            $data['image'] = $this->storeImageAsWebp(
                $request->file('image'),
                'categories'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Category
        |--------------------------------------------------------------------------
        */

        $category->update($data);

        return $this->success(
            new CategoryResource(
                $category->fresh()
            ),
            'Category updated successfully'
        );
    }

    // DELETE /api/admin/categories/{category} (admin)
    public function destroy(Category $category): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Delete Image
        |--------------------------------------------------------------------------
        */

        if (!empty($category->image)) {
            Storage::disk('public')->delete(
                ltrim($category->image, '/')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Category
        |--------------------------------------------------------------------------
        */

        $category->delete();

        return $this->success(
            null,
            'Category deleted successfully'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unique Slug
    |--------------------------------------------------------------------------
    */

    protected function uniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Category::where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($q) => $q->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

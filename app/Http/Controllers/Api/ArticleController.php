<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    use ApiResponseTrait, ImageConverterTrait;

    // GET /api/articles?product_id=1 (public)
    public function index(Request $request): JsonResponse
    {
        $articles = Article::query()
            ->published()
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $id = $request->integer('product_id');
                $q->where(fn ($q2) => $q2->where('product_id', $id)
                    ->orWhereHas('products', fn ($p) => $p->where('products.id', $id)));
            })
            ->with(['images'])
            ->latest('published_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return $this->success(ArticleResource::collection($articles));
    }

    // GET /api/articles/{slug} (public)
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()
            ->where('slug', $slug)
            ->with(['images', 'product.images', 'product.category', 'products' => fn ($q) => $q->active()->with(['images', 'category'])])
            ->firstOrFail();

        return $this->success(new ArticleResource($article));
    }

    // GET /api/admin/articles (admin)
    public function adminIndex(): JsonResponse
    {
        $articles = Article::query()
            ->with(['images', 'products'])
            ->latest()
            ->paginate(20);

        return $this->success(ArticleResource::collection($articles));
    }

    // POST /api/admin/articles (admin)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $productIds = $data['product_ids'] ?? [];
        unset($data['gallery'], $data['product_ids']);

        $data['slug'] = $this->uniqueSlug($data['title_en']);
        $data['is_published'] = $data['is_published'] ?? true;
        $data['published_at'] = $data['published_at'] ?? now();

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeImageAsWebp($request->file('cover_image'), 'articles');
        }

        $article = Article::create($data);

        $article->products()->sync($productIds);

        if ($request->hasFile('gallery')) {
            foreach ($request->file('gallery') as $i => $file) {
                $article->images()->create([
                    'image' => $this->storeImageAsWebp($file, 'articles'),
                    'sort_order' => $i,
                ]);
            }
        }

        return $this->success(
            new ArticleResource($article->load(['images', 'products', 'product'])),
            'Article created',
            201
        );
    }

    // PUT /api/admin/articles/{article} (admin)
    public function update(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate($this->rules(true));

        $hasProducts = array_key_exists('product_ids', $data);
        $productIds = $data['product_ids'] ?? [];
        unset($data['gallery'], $data['product_ids']);

        if (isset($data['title_en']) && $data['title_en'] !== $article->title_en) {
            $data['slug'] = $this->uniqueSlug($data['title_en'], $article->id);
        }

        if ($request->hasFile('cover_image')) {
            if (! empty($article->cover_image)) {
                Storage::disk('public')->delete(ltrim($article->cover_image, '/'));
            }
            $data['cover_image'] = $this->storeImageAsWebp($request->file('cover_image'), 'articles');
        }

        $article->update($data);

        if ($hasProducts) {
            $article->products()->sync($productIds);
        }

        if ($request->hasFile('gallery')) {
            $nextOrder = (int) $article->images()->max('sort_order') + 1;

            foreach ($request->file('gallery') as $i => $file) {
                $article->images()->create([
                    'image' => $this->storeImageAsWebp($file, 'articles'),
                    'sort_order' => $nextOrder + $i,
                ]);
            }
        }

        return $this->success(
            new ArticleResource($article->fresh(['images', 'products', 'product'])),
            'Article updated'
        );
    }

    // DELETE /api/admin/articles/{article} (admin)
    public function destroy(Article $article): JsonResponse
    {
        if (! empty($article->cover_image)) {
            Storage::disk('public')->delete(ltrim($article->cover_image, '/'));
        }

        foreach ($article->images as $image) {
            if (! empty($image->image)) {
                Storage::disk('public')->delete(ltrim($image->image, '/'));
            }
        }

        $article->delete();

        return $this->success(null, 'Article deleted');
    }

    // DELETE /api/admin/articles/{article}/images/{image} (admin)
    public function destroyImage(Article $article, ArticleImage $image): JsonResponse
    {
        if ($image->article_id !== $article->id) {
            return $this->error('Not found', 404);
        }

        if (! empty($image->image)) {
            Storage::disk('public')->delete(ltrim($image->image, '/'));
        }

        $image->delete();

        return $this->success(null, 'Article image deleted');
    }

    protected function rules(bool $update = false): array
    {
        $req = $update ? ['sometimes', 'required'] : ['required'];

        return [
            'product_id'    => ['nullable', 'exists:products,id'],
            'title_ar'      => [...$req, 'string', 'max:255'],
            'title_en'      => [...$req, 'string', 'max:255'],
            'excerpt_ar'    => ['nullable', 'string'],
            'excerpt_en'    => ['nullable', 'string'],
            'content_ar'    => [...$req, 'string'],
            'content_en'    => ['nullable', 'string'],
            'cover_image'   => ['nullable', 'image', 'max:4096'],
            'gallery'       => ['nullable', 'array'],
            'gallery.*'     => ['image', 'max:4096'],
            'product_ids'   => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'is_published'  => ['nullable', 'boolean'],
            'published_at'  => ['nullable', 'date'],
        ];
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'article';
        $slug = $base;
        $i = 1;

        while (
            Article::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
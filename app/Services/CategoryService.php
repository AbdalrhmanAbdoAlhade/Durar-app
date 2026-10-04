<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CategoryService
{
    public function listActive(int $perPage = 20): LengthAwarePaginator
    {
        return Category::query()
            ->active()
            ->orderBy('sort_order')
            ->paginate($perPage);
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('sort_order')
            ->paginate($perPage);
    }

    public function find(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function create(array $data): Category
    {
        $data['slug'] = $this->uniqueSlug($data['name_en']);

        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        if (isset($data['name_en']) && $data['name_en'] !== $category->name_en) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $category->id);
        }

        $category->update($data);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
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

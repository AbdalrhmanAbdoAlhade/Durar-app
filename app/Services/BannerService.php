<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BannerService
{
    public function listActive(?string $type = null, int $perPage = 20): LengthAwarePaginator
    {
        return Banner::query()
            ->active()
            ->when($type, fn ($q) => $q->ofType($type))
            ->with(['bannerable', 'coupon'])
            ->orderBy('sort_order')
            ->paginate($perPage);
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Banner::query()
            ->with(['bannerable', 'coupon'])
            ->orderBy('sort_order')
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Banner
    {
        return Banner::create($data);
    }

    public function update(Banner $banner, array $data): Banner
    {
        $banner->update($data);

        return $banner->fresh(['bannerable', 'coupon']);
    }

    public function delete(Banner $banner): void
    {
        $banner->delete();
    }
}

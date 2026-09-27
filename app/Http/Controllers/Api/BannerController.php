<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected BannerService $banners)
    {
    }

    // GET /api/banners?type=normal|offer (public)
    public function index(Request $request): JsonResponse
    {
        $banners = $this->banners->listActive($request->string('type')->value() ?: null);

        return $this->success(BannerResource::collection($banners));
    }

    // GET /api/admin/banners (admin)
    public function adminIndex(): JsonResponse
    {
        return $this->success(BannerResource::collection($this->banners->listAll()));
    }

    // POST /api/admin/banners (admin)
    public function store(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['image'] = $request->file('image')->store('banners', 'public');

        $banner = $this->banners->create($data);

        return $this->success(new BannerResource($banner->load('bannerable', 'coupon')), 'Banner created', 201);
    }

    // PUT /api/admin/banners/{banner} (admin)
    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($banner->image);
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner = $this->banners->update($banner, $data);

        return $this->success(new BannerResource($banner), 'Banner updated');
    }

    // DELETE /api/admin/banners/{banner} (admin)
    public function destroy(Banner $banner): JsonResponse
    {
        Storage::disk('public')->delete($banner->image);
        $this->banners->delete($banner);

        return $this->success(null, 'Banner deleted');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBannerRequest;
use App\Http\Requests\UpdateBannerRequest;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\BannerService;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    use ApiResponseTrait, ImageConverterTrait;

    public function __construct(
        protected BannerService $banners
    ) {
    }

    // GET /api/banners?type=normal|offer (public)
    public function index(Request $request): JsonResponse
    {
        $banners = $this->banners->listActive(
            $request->string('type')->value() ?: null
        );

        return $this->success(
            BannerResource::collection($banners)
        );
    }

    // GET /api/admin/banners (admin)
    public function adminIndex(): JsonResponse
    {
        return $this->success(
            BannerResource::collection(
                $this->banners->listAll()
            )
        );
    }

    // POST /api/admin/banners (admin)
    public function store(
        StoreBannerRequest $request
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Store Banner Image as WebP
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImageAsWebp(
                $request->file('image'),
                'banners'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Banner
        |--------------------------------------------------------------------------
        */

        $banner = $this->banners->create($data);

        return $this->success(
            new BannerResource(
                $banner->load('bannerable', 'coupon')
            ),
            'Banner created',
            201
        );
    }

    // PUT /api/admin/banners/{banner} (admin)
    public function update(
        UpdateBannerRequest $request,
        Banner $banner
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Replace Banner Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {

            // Delete old image
            if (!empty($banner->image)) {
                Storage::disk('public')->delete(
                    ltrim($banner->image, '/')
                );
            }

            // Store new image as WebP
            $data['image'] = $this->storeImageAsWebp(
                $request->file('image'),
                'banners'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Banner
        |--------------------------------------------------------------------------
        */

        $banner = $this->banners->update(
            $banner,
            $data
        );

        return $this->success(
            new BannerResource($banner),
            'Banner updated'
        );
    }

    // DELETE /api/admin/banners/{banner} (admin)
    public function destroy(
        Banner $banner
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Delete Banner Image
        |--------------------------------------------------------------------------
        */

        if (!empty($banner->image)) {
            Storage::disk('public')->delete(
                ltrim($banner->image, '/')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Banner
        |--------------------------------------------------------------------------
        */

        $this->banners->delete($banner);

        return $this->success(
            null,
            'Banner deleted'
        );
    }
}

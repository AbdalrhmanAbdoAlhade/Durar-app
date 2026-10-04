<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceBidRequest;
use App\Http\Requests\StoreAuctionRequest;
use App\Http\Requests\UpdateAuctionRequest;
use App\Http\Resources\AuctionBidResource;
use App\Http\Resources\AuctionResource;
use App\Models\Auction;
use App\Services\AuctionService;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageConverterTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    use ApiResponseTrait, ImageConverterTrait;

    public function __construct(
        protected AuctionService $auctions
    ) {
    }

    // GET /api/auctions?status=active|upcoming|ended (public)
    public function index(Request $request): JsonResponse
    {
        $auctions = match ($request->string('status')->value()) {
            'upcoming' => $this->auctions->listUpcoming(),
            'ended' => $this->auctions->listEnded(),
            default => $this->auctions->listActive(),
        };

        return $this->success(
            AuctionResource::collection($auctions)
        );
    }

    // GET /api/auctions/{auction} (public)
    public function show(Auction $auction): JsonResponse
    {
        return $this->success(
            new AuctionResource(
                $this->auctions->find($auction->id)
            )
        );
    }

    // GET /api/admin/auctions (admin)
    public function adminIndex(): JsonResponse
    {
        return $this->success(
            AuctionResource::collection(
                $this->auctions->listAll()
            )
        );
    }

    // POST /api/admin/auctions (admin)
    public function store(
        StoreAuctionRequest $request
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Gallery is not a column in auctions table
        |--------------------------------------------------------------------------
        */

        unset($data['gallery']);

        /*
        |--------------------------------------------------------------------------
        | Cover Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeImageAsWebp(
                $request->file('cover_image'),
                'auctions'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create Auction
        |--------------------------------------------------------------------------
        */

        $auction = $this->auctions->create($data);

        /*
        |--------------------------------------------------------------------------
        | Gallery Images
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('gallery')) {
            $paths = [];

            foreach ($request->file('gallery') as $file) {
                $paths[] = $this->storeImageAsWebp(
                    $file,
                    'auctions'
                );
            }

            $this->auctions->addGalleryImages(
                $auction,
                $paths
            );
        }

        return $this->success(
            new AuctionResource(
                $auction->load('images')
            ),
            'Auction created',
            201
        );
    }

    // PUT /api/admin/auctions/{auction} (admin)
    public function update(
        UpdateAuctionRequest $request,
        Auction $auction
    ): JsonResponse {
        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Gallery is not a column in auctions table
        |--------------------------------------------------------------------------
        */

        unset($data['gallery']);

        /*
        |--------------------------------------------------------------------------
        | Replace Cover Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('cover_image')) {

            // Delete old cover image
            if (!empty($auction->cover_image)) {
                Storage::disk('public')->delete(
                    ltrim($auction->cover_image, '/')
                );
            }

            // Store new cover image as WebP
            $data['cover_image'] = $this->storeImageAsWebp(
                $request->file('cover_image'),
                'auctions'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Auction
        |--------------------------------------------------------------------------
        */

        $auction->update($data);

        /*
        |--------------------------------------------------------------------------
        | Add New Gallery Images
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('gallery')) {
            $paths = [];

            foreach ($request->file('gallery') as $file) {
                $paths[] = $this->storeImageAsWebp(
                    $file,
                    'auctions'
                );
            }

            $this->auctions->addGalleryImages(
                $auction,
                $paths
            );
        }

        return $this->success(
            new AuctionResource(
                $auction->fresh('images')
            ),
            'Auction updated'
        );
    }

    // DELETE /api/admin/auctions/{auction} (admin)
    public function destroy(Auction $auction): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Delete Cover Image
        |--------------------------------------------------------------------------
        */

        if (!empty($auction->cover_image)) {
            Storage::disk('public')->delete(
                ltrim($auction->cover_image, '/')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Gallery Images
        |--------------------------------------------------------------------------
        */

        foreach ($auction->images as $image) {
            if (!empty($image->image)) {
                Storage::disk('public')->delete(
                    ltrim($image->image, '/')
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Auction
        |--------------------------------------------------------------------------
        */

        $this->auctions->delete($auction);

        return $this->success(
            null,
            'Auction deleted'
        );
    }

    // POST /api/auctions/{auction}/bids (auth)
    public function placeBid(
        PlaceBidRequest $request,
        Auction $auction
    ): JsonResponse {
        $amount = (float) $request->input('amount');

        $bid = $this->auctions->placeBid(
            $auction,
            $request->user()->id,
            $amount
        );

        return $this->success(
            new AuctionBidResource(
                $bid->load('user')
            ),
            'Bid placed',
            201
        );
    }

    // GET /api/auctions/{auction}/bids (public)
    public function bids(Auction $auction): JsonResponse
    {
        return $this->success(
            AuctionBidResource::collection(
                $auction->bids()
                    ->with('user')
                    ->get()
            )
        );
    }

    // POST /api/admin/auctions/{auction}/close (admin)
    public function close(Auction $auction): JsonResponse
    {
        $closedAuction = $this->auctions->close($auction);

        return $this->success(
            new AuctionResource($closedAuction),
            'Auction closed'
        );
    }
}

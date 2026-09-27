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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuctionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected AuctionService $auctions)
    {
    }

    // GET /api/auctions?status=active|upcoming|ended (public)
    public function index(Request $request): JsonResponse
    {
        $auctions = match ($request->string('status')->value()) {
            'upcoming' => $this->auctions->listUpcoming(),
            'ended' => $this->auctions->listEnded(),
            default => $this->auctions->listActive(),
        };

        return $this->success(AuctionResource::collection($auctions));
    }

    // GET /api/auctions/{auction} (public)
    public function show(Auction $auction): JsonResponse
    {
        return $this->success(new AuctionResource($this->auctions->find($auction->id)));
    }

    // GET /api/admin/auctions (admin)
    public function adminIndex(): JsonResponse
    {
        return $this->success(AuctionResource::collection($this->auctions->listAll()));
    }

    // POST /api/admin/auctions (admin)
    public function store(StoreAuctionRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['gallery']);

        $data['cover_image'] = $request->file('cover_image')->store('auctions', 'public');

        $auction = $this->auctions->create($data);

        if ($request->hasFile('gallery')) {
            $paths = array_map(fn ($file) => $file->store('auctions', 'public'), $request->file('gallery'));
            $this->auctions->addGalleryImages($auction, $paths);
        }

        return $this->success(new AuctionResource($auction->load('images')), 'Auction created', 201);
    }

    // PUT /api/admin/auctions/{auction} (admin)
    public function update(UpdateAuctionRequest $request, Auction $auction): JsonResponse
    {
        $data = $request->validated();
        unset($data['gallery']);

        if ($request->hasFile('cover_image')) {
            Storage::disk('public')->delete($auction->cover_image);
            $data['cover_image'] = $request->file('cover_image')->store('auctions', 'public');
        }

        $auction = $this->auctions->update($auction, $data);

        if ($request->hasFile('gallery')) {
            $paths = array_map(fn ($file) => $file->store('auctions', 'public'), $request->file('gallery'));
            $this->auctions->addGalleryImages($auction, $paths);
        }

        return $this->success(new AuctionResource($auction->load('images')), 'Auction updated');
    }

    // DELETE /api/admin/auctions/{auction} (admin)
    public function destroy(Auction $auction): JsonResponse
    {
        Storage::disk('public')->delete($auction->cover_image);
        foreach ($auction->images as $image) {
            Storage::disk('public')->delete($image->image);
        }

        $this->auctions->delete($auction);

        return $this->success(null, 'Auction deleted');
    }

    // POST /api/auctions/{auction}/bids (auth)
    public function placeBid(PlaceBidRequest $request, Auction $auction): JsonResponse
    {
        $bid = $this->auctions->placeBid($auction, $request->user()->id, (float) $request->input('amount'));

        return $this->success(new AuctionBidResource($bid->load('user')), 'Bid placed', 201);
    }

    // GET /api/auctions/{auction}/bids (public)
    public function bids(Auction $auction): JsonResponse
    {
        return $this->success(AuctionBidResource::collection($auction->bids()->with('user')->get()));
    }

    // POST /api/admin/auctions/{auction}/close (admin)
    public function close(Auction $auction): JsonResponse
    {
        return $this->success(new AuctionResource($this->auctions->close($auction)), 'Auction closed');
    }
}

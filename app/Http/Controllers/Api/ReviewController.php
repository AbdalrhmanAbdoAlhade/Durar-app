<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected ReviewService $reviews)
    {
    }

    // GET /api/products/{product}/reviews (public)
    public function index(int $product): JsonResponse
    {
        return $this->success(ReviewResource::collection($this->reviews->listApprovedForProduct($product)));
    }

    // POST /api/products/{product}/reviews (auth)
    public function store(StoreReviewRequest $request, int $product): JsonResponse
    {
        $review = $this->reviews->create($product, $request->user()->id, $request->validated());

        return $this->success(new ReviewResource($review), 'Review submitted, pending approval', 201);
    }

    // GET /api/admin/reviews/pending (admin)
    public function pending(): JsonResponse
    {
        return $this->success(ReviewResource::collection($this->reviews->listPending()));
    }

    // POST /api/admin/reviews/{review}/approve (admin)
    public function approve(Review $review): JsonResponse
    {
        return $this->success(new ReviewResource($this->reviews->approve($review)), 'Review approved');
    }

    // DELETE /api/admin/reviews/{review} (admin)
    public function destroy(Review $review): JsonResponse
    {
        $this->reviews->reject($review);

        return $this->success(null, 'Review deleted');
    }
}

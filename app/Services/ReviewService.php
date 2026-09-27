<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function listApprovedForProduct(int $productId, int $perPage = 20): LengthAwarePaginator
    {
        return Review::query()
            ->where('product_id', $productId)
            ->approved()
            ->with('user')
            ->latest()
            ->paginate($perPage);
    }

    public function listPending(int $perPage = 20): LengthAwarePaginator
    {
        return Review::query()
            ->pending()
            ->with(['user', 'product'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(int $productId, int $userId, array $data): Review
    {
        Product::findOrFail($productId);

        return Review::updateOrCreate(
            ['product_id' => $productId, 'user_id' => $userId],
            [
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'is_approved' => false,
            ]
        );
    }

    public function approve(Review $review): Review
    {
        $review->update(['is_approved' => true]);

        $this->notifications->sendToUser(
            $review->user,
            'تمت الموافقة على تقييمك',
            'تقييمك للمنتج بقى ظاهر للجميع، شكرًا لمشاركتك.'
        );

        return $review;
    }

    public function reject(Review $review): void
    {
        $review->delete();
    }
}

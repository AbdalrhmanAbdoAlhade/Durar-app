<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\AuctionBid;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuctionService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function listActive(int $perPage = 20): LengthAwarePaginator
    {
        return Auction::query()->active()->with('images')->orderBy('ends_at')->paginate($perPage);
    }

    public function listUpcoming(int $perPage = 20): LengthAwarePaginator
    {
        return Auction::query()->upcoming()->with('images')->orderBy('starts_at')->paginate($perPage);
    }

    public function listEnded(int $perPage = 20): LengthAwarePaginator
    {
        return Auction::query()->ended()->with(['images', 'winner'])->latest('ends_at')->paginate($perPage);
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Auction::query()->with(['images', 'winner'])->latest()->paginate($perPage);
    }

    public function find(int $id): Auction
    {
        return Auction::with(['images', 'bids.user', 'winner'])->findOrFail($id);
    }

    public function create(array $data): Auction
    {
        $data['slug'] = $this->uniqueSlug($data['name_en']);
        $data['current_price'] = $data['starting_price'];
        $data['status'] = $data['status'] ?? 'scheduled';

        return Auction::create($data);
    }

    public function update(Auction $auction, array $data): Auction
    {
        if (isset($data['name_en']) && $data['name_en'] !== $auction->name_en) {
            $data['slug'] = $this->uniqueSlug($data['name_en'], $auction->id);
        }

        $auction->update($data);

        return $auction->fresh();
    }

    public function delete(Auction $auction): void
    {
        $auction->delete();
    }

    public function addGalleryImages(Auction $auction, array $paths): void
    {
        $nextOrder = (int) $auction->images()->max('sort_order') + 1;

        foreach ($paths as $i => $path) {
            $auction->images()->create([
                'image' => $path,
                'sort_order' => $nextOrder + $i,
            ]);
        }
    }

    /**
     * Place a bid on an auction. Validates the auction is biddable and the amount
     * meets the minimum next bid, then atomically updates the current price.
     */
    public function placeBid(Auction $auction, int $userId, float $amount): AuctionBid
    {
        return DB::transaction(function () use ($auction, $userId, $amount) {
            $auction = Auction::where('id', $auction->id)->lockForUpdate()->first();

            if (! $auction->isBiddable()) {
                abort(422, 'This auction is not open for bidding right now');
            }

            if ($amount < $auction->minimumNextBid()) {
                abort(422, 'Bid amount must be at least '.$auction->minimumNextBid());
            }

            $previousTopBid = $auction->bids()->orderByDesc('amount')->first();

            $bid = $auction->bids()->create([
                'user_id' => $userId,
                'amount' => $amount,
            ]);

            $auction->update(['current_price' => $amount]);

            if ($previousTopBid && $previousTopBid->user_id !== $userId) {
                $this->notifications->sendToUser(
                    $previousTopBid->user,
                    'تم تجاوز مزايدتك',
                    "حد زايد بأكتر منك على مزاد \"{$auction->name_ar}\". المزايدة الحالية: {$amount}"
                );
            }

            return $bid;
        });
    }

    /**
     * Close an auction: mark it ended and set the winner to the highest bidder (if any).
     */
    public function close(Auction $auction): Auction
    {
        $topBid = $auction->bids()->orderByDesc('amount')->first();

        $auction->update([
            'status' => 'ended',
            'winner_id' => $topBid?->user_id,
        ]);

        $auction = $auction->fresh(['winner']);

        if ($auction->winner) {
            $this->notifications->sendToUser(
                $auction->winner,
                'مبروك، كسبت المزاد!',
                "أنت الفايز في مزاد \"{$auction->name_ar}\" بسعر {$auction->current_price}. أكمل الدفع لتأكيد الطلب."
            );
        }

        return $auction;
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Auction::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

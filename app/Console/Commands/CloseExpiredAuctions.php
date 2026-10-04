<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Services\AuctionService;
use Illuminate\Console\Command;

class CloseExpiredAuctions extends Command
{
    protected $signature = 'auctions:close-expired';

    protected $description = 'Close auctions whose ends_at has passed';

    public function handle(AuctionService $auctions): int
    {
        $expired = Auction::query()
            ->whereIn('status', ['active', 'scheduled'])
            ->where('ends_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired auctions found.');
            return self::SUCCESS;
        }

        foreach ($expired as $auction) {
            // لو لسه scheduled ومبدأش أصلاً، سيبه
            if ($auction->status === 'scheduled' && $auction->starts_at > now()) {
                continue;
            }

            $auctions->close($auction);
            $this->info("Closed auction #{$auction->id} ({$auction->name_en})");
        }

        return self::SUCCESS;
    }
}
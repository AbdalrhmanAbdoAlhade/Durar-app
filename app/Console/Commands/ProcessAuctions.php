<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Services\AuctionService;
use Illuminate\Console\Command;

class ProcessAuctions extends Command
{
    protected $signature = 'auctions:process';

    protected $description = 'Activate scheduled auctions whose start time has arrived, and close active auctions whose end time has passed';

    public function handle(AuctionService $auctions): int
    {
        $activated = Auction::where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->update(['status' => 'active']);

        $this->info("Activated {$activated} auction(s).");

        $expired = Auction::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expired as $auction) {
            $auctions->close($auction);
            $this->info("Closed auction #{$auction->id} ({$auction->name_en}).");
        }

        return self::SUCCESS;
    }
}

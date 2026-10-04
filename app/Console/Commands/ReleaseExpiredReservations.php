<?php

namespace App\Console\Commands;

use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release-expired';

    protected $description = 'Release expired stock reservations and mark related unpaid orders as failed/cancelled';

    public function handle(): int
    {
        $expired = StockReservation::query()
            ->where('status', 'reserved')
            ->where('expires_at', '<', now())
            ->with('order')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired reservations found.');
            return self::SUCCESS;
        }

        $releasedCount = 0;
        $ordersAffected = [];

        DB::transaction(function () use ($expired, &$releasedCount, &$ordersAffected) {
            foreach ($expired as $reservation) {
                $reservation->update(['status' => 'released']);
                $releasedCount++;

                $order = $reservation->order;

                if (
                    $order &&
                    ! isset($ordersAffected[$order->id]) &&
                    in_array($order->payment_status, ['unpaid', 'failed'], true) &&
                    $order->status === 'pending'
                ) {
                    // لو كل حجوزات الطلب اتتحررت أو انتهت
                    $stillReserved = $order->reservations()
                        ->where('status', 'reserved')
                        ->exists();

                    if (! $stillReserved) {
                        $order->update([
                            'payment_status' => 'failed',
                            'status'         => 'cancelled',
                        ]);

                        $ordersAffected[$order->id] = $order->order_number;
                    }
                }
            }
        });

        $this->info("Released {$releasedCount} reservation(s).");

        if (! empty($ordersAffected)) {
            $this->info('Orders cancelled: ' . implode(', ', $ordersAffected));
            Log::info('Expired reservations released', [
                'reservations_count' => $releasedCount,
                'orders'             => array_values($ordersAffected),
            ]);
        }

        return self::SUCCESS;
    }
}
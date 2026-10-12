<?php

namespace App\Console\Commands;

use App\Models\TicketBooking;
use App\Services\TicketService;
use Illuminate\Console\Command;

class GenerateMissingBarcodeTokens extends Command
{
    protected $signature = 'tickets:generate-barcodes 
                            {--force : إعادة توليد الباركود حتى لو موجود}';

    protected $description = 'توليد barcode_token لكل الحجوزات اللي مش عندها باركود';

    public function handle(TicketService $ticketService): int
    {
        $query = TicketBooking::query();

        if (! $this->option('force')) {
            $query->whereNull('barcode_token');
        }

        $bookings = $query->get();

        if ($bookings->isEmpty()) {
            $this->info('مفيش حجوزات محتاجة باركود.');
            return self::SUCCESS;
        }

        $this->info("هيتولد باركود لـ {$bookings->count()} حجز...");

        $bar = $this->output->createProgressBar($bookings->count());
        $bar->start();

        $success = 0;
        $failed  = 0;

        foreach ($bookings as $booking) {
            try {
                $token = $ticketService->generateSecureBarcodeToken(
                    $booking->id,
                    $booking->booking_code
                );

                $booking->barcode_token = $token;
                $booking->save();

                $success++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("فشل الحجز #{$booking->id}: " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("تم بنجاح: {$success}");
        if ($failed > 0) {
            $this->warn("فشل: {$failed}");
        }

        return self::SUCCESS;
    }
}
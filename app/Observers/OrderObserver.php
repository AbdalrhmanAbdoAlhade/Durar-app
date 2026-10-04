<?php

namespace App\Observers;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status') || $order->type !== 'product') {
            return;
        }

        $old = $order->getOriginal('payment_status');
        $new = $order->payment_status;

        if ($new === 'paid' && $old !== 'paid') {
            $this->adjustSales($order, +1);
        } elseif ($old === 'paid' && $new !== 'paid') {
            // استرجاع / إلغاء دفع
            $this->adjustSales($order, -1);
        }
    }

    protected function adjustSales(Order $order, int $direction): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;

            if ($direction > 0) {
                DB::table('products')
                    ->where('id', $item->product_id)
                    ->increment('sales_count', $qty);
            } else {
                DB::table('products')
                    ->where('id', $item->product_id)
                    ->update(['sales_count' => DB::raw("GREATEST(sales_count - {$qty}, 0)")]);
            }
        }
    }
}
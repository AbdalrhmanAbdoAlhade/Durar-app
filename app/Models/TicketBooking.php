<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketBooking extends Model
{
    protected $fillable = [
        'booking_code',
        'barcode_token',     
        'user_id',
        'customer_name',
        'national_id',
        'phone',
        'subtotal',
        'discount_amount',
        'total',
        'status',
        'payment_status',
        'order_id',
    ];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketBookingItem::class);
    }
}
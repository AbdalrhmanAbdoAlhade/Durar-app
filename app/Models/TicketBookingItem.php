<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketBookingItem extends Model
{
    protected $fillable = [
        'ticket_booking_id',
        'ticket_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'total',
    ];

    protected $casts = [
        'unit_price'       => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'total'            => 'decimal:2',
        'quantity'         => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
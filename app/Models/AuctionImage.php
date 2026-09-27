<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionImage extends Model
{
    protected $fillable = [
        'auction_id',
        'image',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
}

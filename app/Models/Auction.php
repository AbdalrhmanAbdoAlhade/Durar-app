<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Auction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'cover_image',
        'metadata',
        'starting_price',
        'current_price',
        'min_bid_increment',
        'starts_at',
        'ends_at',
        'status',
        'winner_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'starting_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'min_bid_increment' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(AuctionImage::class)->orderBy('sort_order');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class)->orderByDesc('amount');
    }

    public function highestBid(): HasOne
    {
        return $this->hasOne(AuctionBid::class)->orderByDesc('amount');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    public function isBiddable(): bool
    {
        return $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }

    public function minimumNextBid(): float
    {
        return round((float) $this->current_price + (float) $this->min_bid_increment, 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'cover_image',
        'price',
        'discount_percentage',
        'quantity',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'quantity' => 'integer',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function banners(): MorphMany
    {
        return $this->morphMany(Banner::class, 'bannerable');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Final price after applying the product's own discount percentage.
     */
    public function getFinalPriceAttribute(): float
    {
        if ($this->discount_percentage <= 0) {
            return (float) $this->price;
        }

        return round((float) $this->price * (1 - $this->discount_percentage / 100), 2);
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->approvedReviews()->avg('rating'), 1);
    }
}

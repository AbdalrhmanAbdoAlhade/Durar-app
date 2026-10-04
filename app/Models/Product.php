<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasTranslations;

class Product extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    // sales_count مش هنا عن قصد: بيتحدث من OrderObserver بس
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
        'is_rare',
        'metadata',
        'is_active',
        // ===== الحقول الجديدة =====
        'classification',
        'hardness',
        'origin_country',
        'origin_details',
        'weight',
        'weight_unit',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'discount_percentage' => 'integer',
        'quantity'            => 'integer',
        'sales_count'         => 'integer',
        'is_rare'             => 'boolean',
        'metadata'            => 'array',
        'is_active'           => 'boolean',
        'weight'              => 'decimal:2',
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

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
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

    public function scopeRare($query)
    {
        return $query->where('is_rare', true);
    }

    public function getNameAttribute(): string
    {
        return $this->localized('name') ?? '';
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->localized('description');
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

    /**
     * الكمية المتاحة = المخزون الفعلي - الحجوزات النشطة
     */
    public function getAvailableQuantityAttribute(): int
    {
        $reserved = $this->reservations()
            ->where('status', 'reserved')
            ->where('expires_at', '>', now())
            ->sum('quantity');

        return max(0, $this->quantity - $reserved);
    }
}
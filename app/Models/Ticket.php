<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use SoftDeletes;

       protected $fillable = [
        'name_ar',
        'name_en',
        'details_ar',
        'details_en',
        'price',
        'discount_amount',
        'event_start',      
        'event_end',        
        'location',
        'quantity',
        'is_active',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'event_start'     => 'datetime',
        'event_end'       => 'datetime',
        'is_active'       => 'boolean',
        'quantity'        => 'integer',
    ];

    /** نخفي الأعمدة اللغوية من الـ JSON ونرجّع name و details حسب اللغة */
    protected $hidden = [
        'name_ar',
        'name_en',
        'details_ar',
        'details_en',
    ];

    protected $appends = [
        'name',
        'details',
    ];

    public function bookingItems(): HasMany
    {
        return $this->hasMany(TicketBookingItem::class);
    }

    /** الاسم حسب لغة التطبيق الحالية (مع fallback للغة التانية) */
    public function getNameAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->name_en ?: $this->name_ar)
            : ($this->name_ar ?: $this->name_en);
    }

    /** التفاصيل حسب لغة التطبيق الحالية (مع fallback للغة التانية) */
    public function getDetailsAttribute(): ?string
    {
        return app()->getLocale() === 'en'
            ? ($this->details_en ?: $this->details_ar)
            : ($this->details_ar ?: $this->details_en);
    }

    /** هل الكمية غير محدودة؟ */
    public function isUnlimited(): bool
    {
        return is_null($this->quantity) || $this->quantity < 0;
    }

    /** السعر النهائي بعد الخصم */
    public function finalPrice(): float
    {
        return max(0, (float) $this->price - (float) $this->discount_amount);
    }

    /** هل الكمية كافية؟ */
    public function hasAvailableQuantity(int $requested): bool
    {
        if ($this->isUnlimited()) {
            return true;
        }

        return $this->quantity >= $requested;
    }
}
<?php

namespace App\Traits;

trait HasTranslations
{
    /**
     * يرجع قيمة الحقل حسب اللغة الحالية.
     * مثال: localized('name') → name_ar أو name_en
     */
    public function localized(string $field): ?string
    {
        $locale = app()->getLocale();
        $localized = $this->{"{$field}_{$locale}"} ?? null;

        if ($localized !== null && $localized !== '') {
            return $localized;
        }

        // fallback
        $fallback = config('app.fallback_locale', 'en');
        return $this->{"{$field}_{$fallback}"}
            ?? $this->{"{$field}_ar"}
            ?? $this->{"{$field}_en"}
            ?? null;
    }
}
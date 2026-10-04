<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

trait ResolvesLocale
{
    /** هل العميل طلب لغة معيّنة؟ */
    protected function isLocaleForced(Request $request): bool
    {
        return (bool) $request->attributes->get('locale_forced', false);
    }

    /** اللغة المطلوبة (ar أو en) */
    protected function apiLocale(Request $request): string
    {
        return $request->attributes->get('api_locale')
            ?? app()->getLocale();
    }

    /**
     * قيمة مترجمة حسب اللغة.
     * $field = 'name' → يقرأ name_ar أو name_en
     */
    protected function localized(Request $request, string $field): ?string
    {
        $locale = $this->apiLocale($request);
        $value = $this->{"{$field}_{$locale}"} ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        // fallback
        return $this->{"{$field}_ar"}
            ?? $this->{"{$field}_en"}
            ?? null;
    }
}
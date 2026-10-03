<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * اللغات المدعومة في التطبيق.
     */
    protected array $supported = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        // اختياري: رجّع الـ Content-Language في الـ response
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    protected function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language');

        if (!$header) {
            return config('app.locale', 'ar'); // الديفولت عربي
        }

        // مثال: "en-US,en;q=0.9,ar;q=0.8" أو "ar" أو "en"
        $preferred = $request->getPreferredLanguage($this->supported);

        return $preferred ?: config('app.locale', 'ar');
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supported = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Accept-Language');

        // مفيش هيدر → متعملش setLocale، وسيب الـ Resources تعرض الكل
        if ($header) {
            $locale = $request->getPreferredLanguage($this->supported)
                ?: config('app.locale', 'ar');

            app()->setLocale($locale);
            $request->attributes->set('locale_forced', true);
            $request->attributes->set('api_locale', $locale);
        } else {
            $request->attributes->set('locale_forced', false);
        }

        $response = $next($request);

        if ($request->attributes->get('locale_forced')) {
            $response->headers->set(
                'Content-Language',
                $request->attributes->get('api_locale')
            );
        }

        return $response;
    }
}
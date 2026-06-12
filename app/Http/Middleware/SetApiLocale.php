<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->expectsJson() || ! $request->hasHeader('Accept-Language')) {
            return $next($request);
        }

        /**
         * The header has been confirmed
         *
         * @var array<string>|string
         */
        $acceptedLanguages = $request->header('Accept-Language');

        if (is_array($acceptedLanguages)) {
            $acceptedLanguages = implode($acceptedLanguages);
        }

        // Extract the first valid lang
        $locale = explode(',', $acceptedLanguages)[0];
        $locale = strtok($locale, ';'); // Removes possible parameters like ";q=0.9"

        // Prevent weird edge cases
        if (! is_string($locale)) {
            return $next($request);
        }

        // Check if the locale is valid before setting it
        $validLocales = array_keys(config('app.locales', []));
        if (in_array($locale, $validLocales)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

if (! function_exists('getSupportedLocales')) {
  /**
     * Get supported locales in a flexible way.
     * 
     * @return array
     */
    function getSupportedLocales(): array
    {
        $locales = config('meta.supported_locales', []);

        if (array_values($locales) === $locales) {
            return $locales;
        }

        return array_keys($locales);
    }
}
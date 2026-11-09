<?php

namespace Omnishare;

/**
 * omnishare's own labels (translations/<locale>.php, plain PHP arrays by
 * label key), for an application that has none of its own: English and
 * French. An application with a translator imports these files into it, or
 * keeps its own words under its own keys.
 */
final class Labels
{
    /** @var array<string, array<string, string>> */
    private static array $catalogues = [];

    /** @return list<string> the locales omnishare has labels in */
    public static function locales(): array
    {
        return ['en', 'fr'];
    }

    /** @return array<string, string> the labels of a locale ("fr", "fr_FR", "fr-CA"...), English when omnishare has none in it */
    public static function catalogue(string $locale): array
    {
        $language = strtolower(substr($locale, 0, 2));
        if (!\in_array($language, self::locales(), true)) {
            $language = 'en';
        }
        if (!isset(self::$catalogues[$language])) {
            /** @var array<string, string> $labels */
            $labels = require __DIR__.'/translations/'.$language.'.php';
            self::$catalogues[$language] = $labels;
        }

        return self::$catalogues[$language];
    }

    /** A label, its key itself when there is none. */
    public static function get(string $key, string $locale = 'en'): string
    {
        return self::catalogue($locale)[$key] ?? $key;
    }
}

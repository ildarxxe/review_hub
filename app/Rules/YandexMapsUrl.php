<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class YandexMapsUrl implements ValidationRule
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_HOSTS = [
        'yandex.ru',
        'www.yandex.ru',
        'maps.yandex.ru',
        'yandex.com',
        'www.yandex.com',
        'maps.yandex.com',
        'yandex.kz',
        'www.yandex.kz',
        'maps.yandex.kz',
        'yandex.by',
        'www.yandex.by',
        'maps.yandex.by',
        'yandex.uz',
        'www.yandex.uz',
        'maps.yandex.uz',
        'yandex.com.tr',
        'www.yandex.com.tr',
        'maps.yandex.com.tr',
    ];

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Укажите ссылку на организацию в Яндекс.Картах.');

            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        $isOrganizationPath = preg_match(
            '#^/maps/((?:[^/]+/){0,3}org/[^/]+/\d+|-/[^/]+)(?:/|$)#u',
            $path,
        ) === 1;

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ! in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || (! $isOrganizationPath && ! $this->hasPoiOrganization($path, $parts['query'] ?? ''))
        ) {
            $fail('Укажите корректную ссылку на карточку организации в Яндекс.Картах.');
        }
    }

    private function hasPoiOrganization(string $path, string $query): bool
    {
        if (preg_match('#^/maps(?:/[^/]+){0,3}/?$#u', $path) !== 1) {
            return false;
        }

        parse_str($query, $parameters);
        $poiUri = $parameters['poi']['uri'] ?? null;

        if (! is_string($poiUri)) {
            return false;
        }

        $parts = parse_url($poiUri);

        if (($parts['scheme'] ?? '') !== 'ymapsbm1' || ($parts['host'] ?? '') !== 'org') {
            return false;
        }

        parse_str($parts['query'] ?? '', $poiParameters);
        $oid = $poiParameters['oid'] ?? null;

        return is_string($oid) && ctype_digit($oid);
    }
}

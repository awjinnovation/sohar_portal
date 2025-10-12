<?php

namespace App\Helpers;

class LocaleHelper
{
    /**
     * Get the locale from request header or query parameter
     * Defaults to 'en'
     */
    public static function getLocale(): string
    {
        $locale = request()->header('Accept-Language')
                  ?? request()->query('locale')
                  ?? request()->query('lang')
                  ?? 'en';

        // Extract first two characters for language code (e.g., 'en-US' -> 'en')
        $locale = strtolower(substr($locale, 0, 2));

        // Only support 'en' and 'ar'
        return in_array($locale, ['en', 'ar']) ? $locale : 'en';
    }

    /**
     * Get localized field value
     * Returns field value based on locale (field or field_ar)
     */
    public static function getLocalizedField($model, string $field): ?string
    {
        $locale = self::getLocale();

        if ($locale === 'ar') {
            $arField = $field . '_ar';
            return $model->{$arField} ?? $model->{$field};
        }

        return $model->{$field};
    }

    /**
     * Format model with localized fields
     * Returns array with localized field names
     */
    public static function localizeModel($model, array $fields): array
    {
        $locale = self::getLocale();
        $result = [];

        foreach ($fields as $field) {
            if ($locale === 'ar' && isset($model->{$field . '_ar'})) {
                $result[$field] = $model->{$field . '_ar'};
            } else {
                $result[$field] = $model->{$field};
            }
        }

        return $result;
    }

    /**
     * Add both language versions to response
     * Returns object with en and ar properties
     */
    public static function getBilingualField($model, string $field): object
    {
        return (object) [
            'en' => $model->{$field} ?? null,
            'ar' => $model->{$field . '_ar'} ?? null,
        ];
    }
}

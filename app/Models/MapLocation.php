<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MapLocation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'latitude',
        'longitude',
        'icon',
        'color',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    /**
     * Get all available location types with translations
     */
    public static function getTypes(): array
    {
        return [
            'stage' => [
                'en' => 'Stage',
                'ar' => 'مسرح',
                'icon' => 'theater_comedy',
                'color' => '#9C27B0', // Purple
            ],
            'restaurant' => [
                'en' => 'Restaurant',
                'ar' => 'مطعم',
                'icon' => 'restaurant',
                'color' => '#FF5722', // Deep Orange
            ],
            'parking' => [
                'en' => 'Parking',
                'ar' => 'موقف سيارات',
                'icon' => 'local_parking',
                'color' => '#2196F3', // Blue
            ],
            'info' => [
                'en' => 'Information',
                'ar' => 'معلومات',
                'icon' => 'info',
                'color' => '#00BCD4', // Cyan
            ],
            'shopping' => [
                'en' => 'Shopping',
                'ar' => 'تسوق',
                'icon' => 'shopping_bag',
                'color' => '#E91E63', // Pink
            ],
            'restroom' => [
                'en' => 'Restroom',
                'ar' => 'دورات مياه',
                'icon' => 'wc',
                'color' => '#607D8B', // Blue Grey
            ],
            'facilities' => [
                'en' => 'Facilities',
                'ar' => 'مرافق',
                'icon' => 'business',
                'color' => '#795548', // Brown
            ],
            'entertainment' => [
                'en' => 'Entertainment',
                'ar' => 'ترفيه',
                'icon' => 'celebration',
                'color' => '#FF9800', // Orange
            ],
            'food' => [
                'en' => 'Food',
                'ar' => 'طعام',
                'icon' => 'restaurant',
                'color' => '#FFC107', // Amber
            ],
            'emergency' => [
                'en' => 'Emergency',
                'ar' => 'طوارئ',
                'icon' => 'emergency',
                'color' => '#F44336', // Red
            ],
            'first_aid' => [
                'en' => 'First Aid',
                'ar' => 'إسعافات أولية',
                'icon' => 'medical_services',
                'color' => '#FF1744', // Red Accent
            ],
            'heritage' => [
                'en' => 'Heritage Site',
                'ar' => 'موقع تراثي',
                'icon' => 'museum',
                'color' => '#8D6E63', // Brown
            ],
            'fort' => [
                'en' => 'Fort',
                'ar' => 'قلعة',
                'icon' => 'castle',
                'color' => '#6D4C41', // Dark Brown
            ],
            'beach' => [
                'en' => 'Beach',
                'ar' => 'شاطئ',
                'icon' => 'beach_access',
                'color' => '#4FC3F7', // Light Blue
            ],
            'venue' => [
                'en' => 'Venue',
                'ar' => 'مكان',
                'icon' => 'place',
                'color' => '#4CAF50', // Green
            ],
        ];
    }

    /**
     * Get type translation
     */
    public function getTypeTranslation(string $locale = 'en'): string
    {
        $types = self::getTypes();
        return $types[$this->type][$locale] ?? $this->type;
    }

    /**
     * Get type icon
     */
    public function getTypeIcon(): string
    {
        $types = self::getTypes();
        return $types[$this->type]['icon'] ?? 'place';
    }

    /**
     * Get type color
     */
    public function getTypeColor(): string
    {
        $types = self::getTypes();
        return $types[$this->type]['color'] ?? '#4A90E2';
    }

    /**
     * Scope to filter by active status
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}

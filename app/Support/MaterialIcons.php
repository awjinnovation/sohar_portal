<?php

namespace App\Support;

/**
 * Material Symbols Rounded names offered by the admin icon picker for
 * places, categories, collections, items and pages. The app renders the
 * same names (docs/places_content_contract.md §2). Any other valid
 * ligature name typed by hand is accepted too (validated by pattern).
 */
class MaterialIcons
{
    public const PATTERN = '/^[a-z0-9_]{2,64}$/';

    public const LIST = [
        // Places & landmarks
        'fort', 'castle', 'mosque', 'museum', 'account_balance', 'monument',
        'location_city', 'apartment', 'home_work', 'cottage', 'villa', 'holiday_village', 'storefront', 'store', 'shopping_bag',
        'shopping_cart', 'local_mall', 'sell', 'local_grocery_store',
        // Nature & outdoors
        'beach_access', 'waves', 'water', 'sailing', 'kayaking', 'surfing', 'scuba_diving', 'pool', 'park', 'forest', 'nature',
        'nature_people', 'eco', 'grass', 'landscape', 'terrain', 'hiking', 'camping', 'cabin', 'sunny', 'wb_twilight',
        'nights_stay', 'water_drop', 'spa', 'filter_hdr', 'volcano', 'agriculture', 'yard', 'emoji_nature', 'pets',
        // Food & leisure
        'restaurant', 'restaurant_menu', 'local_cafe', 'coffee', 'bakery_dining', 'icecream', 'lunch_dining', 'ramen_dining',
        'local_dining', 'kebab_dining', 'set_meal', 'fastfood', 'cake', 'cookie',
        // Events & culture
        'stadium', 'sports_soccer', 'sports_motorsports', 'directions_car', 'two_wheeler', 'pedal_bike', 'directions_bike',
        'sports_kabaddi', 'sports_handball', 'theater_comedy', 'theaters', 'movie', 'music_note', 'piano', 'mic',
        'celebration', 'festival', 'attractions', 'local_activity', 'confirmation_number', 'palette', 'brush',
        'photo_camera', 'photo_library', 'auto_stories', 'menu_book', 'history_edu', 'school', 'science', 'toys', 'child_care',
        'family_restroom', 'stroller', 'skateboarding',
        // Services & facilities
        'info', 'help', 'help_center', 'support_agent', 'local_parking', 'ev_station', 'local_gas_station', 'wc', 'family_restroom',
        'accessible', 'elevator', 'medical_services', 'local_hospital', 'emergency', 'local_police', 'local_pharmacy',
        'health_and_safety', 'security', 'search', 'luggage', 'checkroom', 'wifi', 'atm', 'payments',
        'account_balance_wallet', 'credit_card', 'currency_exchange', 'local_atm', 'charging_station', 'power',
        'water_full', 'recycling', 'delete', 'smoke_free', 'no_food',
        // Transport
        'directions_bus', 'directions_boat', 'flight', 'local_taxi', 'train', 'tram', 'subway', 'airport_shuttle',
        'directions_walk', 'route', 'map', 'explore', 'near_me', 'navigation', 'place', 'location_on', 'pin_drop',
        'signpost', 'traffic', 'garage',
        // Content & communication
        'handshake', 'workspace_premium', 'verified', 'star', 'favorite', 'thumb_up', 'campaign', 'newspaper', 'article',
        'description', 'checklist', 'rule', 'event', 'event_available', 'schedule', 'calendar_month', 'call', 'mail',
        'language', 'public', 'qr_code_2', 'diversity_3', 'groups', 'person', 'volunteer_activism', 'redeem', 'card_giftcard',
        'local_offer', 'loyalty', 'shield', 'policy', 'gavel', 'lightbulb', 'tips_and_updates', 'quiz', 'question_answer',
    ];

    /** @return list<string> unique names, in order */
    public static function all(): array
    {
        return array_values(array_unique(self::LIST));
    }

    public static function valid(?string $name): bool
    {
        return $name === null || $name === '' || (bool) preg_match(self::PATTERN, $name);
    }
}

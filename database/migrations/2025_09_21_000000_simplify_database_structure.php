<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Consolidate restaurant data into main restaurants table
        if (Schema::hasColumn('restaurants', 'features')) {
            // Already consolidated
        } else {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->json('features')->nullable()->after('cuisine_type_ar');
                $table->json('images')->nullable()->after('features');
                $table->json('opening_hours')->nullable()->after('images');
                $table->text('menu_url')->nullable()->after('opening_hours');
                $table->boolean('has_delivery')->default(false)->after('menu_url');
                $table->boolean('has_parking')->default(false)->after('has_delivery');
                $table->boolean('has_wifi')->default(false)->after('has_parking');
            });

            // Migrate data from related tables
            if (Schema::hasTable('restaurant_features')) {
                $restaurants = DB::table('restaurants')->get();
                foreach ($restaurants as $restaurant) {
                    $features = DB::table('restaurant_features')
                        ->where('restaurant_id', $restaurant->id)
                        ->pluck('feature')
                        ->toArray();

                    $images = DB::table('restaurant_images')
                        ->where('restaurant_id', $restaurant->id)
                        ->pluck('image_url')
                        ->toArray();

                    $hours = DB::table('restaurant_opening_hours')
                        ->where('restaurant_id', $restaurant->id)
                        ->get()
                        ->map(function ($hour) {
                            return [
                                'day' => $hour->day,
                                'day_ar' => $hour->day_ar,
                                'opening' => $hour->opening_time,
                                'closing' => $hour->closing_time
                            ];
                        })
                        ->toArray();

                    DB::table('restaurants')
                        ->where('id', $restaurant->id)
                        ->update([
                            'features' => json_encode($features),
                            'images' => json_encode($images),
                            'opening_hours' => json_encode($hours)
                        ]);
                }
            }
        }

        // 2. Create simplified locations table
        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_ar');
                $table->enum('type', ['restaurant', 'activity', 'service', 'emergency', 'parking', 'restroom', 'first_aid']);
                $table->text('description')->nullable();
                $table->text('description_ar')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('address')->nullable();
                $table->string('address_ar')->nullable();
                $table->string('contact_number')->nullable();
                $table->json('additional_info')->nullable(); // For any type-specific data
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Migrate emergency contacts
            if (Schema::hasTable('emergency_contacts')) {
                $emergencyContacts = DB::table('emergency_contacts')->get();
                foreach ($emergencyContacts as $contact) {
                    DB::table('locations')->insert([
                        'name' => $contact->service_name,
                        'name_ar' => $contact->service_name_ar,
                        'type' => 'emergency',
                        'description' => $contact->type,
                        'description_ar' => $contact->type,
                        'contact_number' => $contact->phone_number,
                        'address' => $contact->location,
                        'address_ar' => $contact->location_ar,
                        'additional_info' => json_encode([
                            'is_24_hours' => $contact->is_24_hours,
                            'secondary_phone' => $contact->secondary_phone,
                            'type' => $contact->type
                        ]),
                        'is_active' => $contact->is_active,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at
                    ]);
                }
            }

            // Migrate map locations
            if (Schema::hasTable('map_locations')) {
                $mapLocations = DB::table('map_locations')->get();
                foreach ($mapLocations as $location) {
                    DB::table('locations')->insert([
                        'name' => $location->name,
                        'name_ar' => $location->name_ar,
                        'type' => 'service', // Default type, adjust as needed
                        'description' => $location->description,
                        'description_ar' => $location->description_ar,
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                        'is_active' => $location->is_active ?? true,
                        'created_at' => $location->created_at,
                        'updated_at' => $location->updated_at
                    ]);
                }
            }

            // Migrate first aid stations
            if (Schema::hasTable('first_aid_stations')) {
                $stations = DB::table('first_aid_stations')->get();
                foreach ($stations as $station) {
                    DB::table('locations')->insert([
                        'name' => $station->name,
                        'name_ar' => $station->name_ar,
                        'type' => 'first_aid',
                        'address' => $station->location,
                        'address_ar' => $station->location_ar,
                        'latitude' => $station->latitude,
                        'longitude' => $station->longitude,
                        'contact_number' => $station->contact_number,
                        'additional_info' => json_encode([
                            'services' => $station->services_available,
                            'services_ar' => $station->services_available_ar,
                            'operating_hours' => $station->operating_hours
                        ]),
                        'is_active' => $station->is_active,
                        'created_at' => $station->created_at,
                        'updated_at' => $station->updated_at
                    ]);
                }
            }
        }

        // 3. Consolidate event pricing into events table
        if (!Schema::hasColumn('events', 'pricing')) {
            Schema::table('events', function (Blueprint $table) {
                $table->json('pricing')->nullable()->after('capacity');
            });

            // Migrate ticket pricing data
            if (Schema::hasTable('ticket_pricing')) {
                $events = DB::table('events')->get();
                foreach ($events as $event) {
                    $pricing = DB::table('ticket_pricing')
                        ->where('event_id', $event->id)
                        ->get()
                        ->map(function ($price) {
                            return [
                                'type' => $price->ticket_type,
                                'price' => $price->price,
                                'quantity' => $price->available_quantity,
                                'benefits' => $price->benefits,
                                'benefits_ar' => $price->benefits_ar
                            ];
                        })
                        ->toArray();

                    if (!empty($pricing)) {
                        DB::table('events')
                            ->where('id', $event->id)
                            ->update(['pricing' => json_encode($pricing)]);
                    }
                }
            }
        }

        // 4. Drop unnecessary tables
        $tablesToDrop = [
            'heritage_villages',
            'village_attractions',
            'village_images',
            'craft_demonstrations',
            'craft_demonstration_schedules',
            'traditional_activities',
            'cultural_timeline_events',
            'photo_spots',
            'photography_tips',
            'restaurant_features',
            'restaurant_images',
            'restaurant_opening_hours',
            'ticket_pricing',
            'emergency_contacts',
            'map_locations',
            'location_categories',
            'first_aid_stations',
            'health_tips'
        ];

        foreach ($tablesToDrop as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration, reversing would require recreating all tables
        // and splitting the data back, which is complex and may result in data loss
        throw new Exception('This migration cannot be reversed. Please restore from backup.');
    }
};
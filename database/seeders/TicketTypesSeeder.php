<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTicketType;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Ticket types per event (docs/ticket_types_contract.md §1). Every event
 * already has its `general` type (created with the event at its price).
 *
 * north-batinah: Sohar Festival keeps its verified General price (OMR 0.500);
 * Family, Child, Senior and VIP are SAMPLE types with PLACEHOLDER prices for
 * the organizer to edit or switch off (see README).
 * demo: a full example set (VIP with its own capacity, student ID check,
 * group ticket admitting 5, a gate-only door ticket, free child ticket,
 * family ticket admitting 4, a closed early-bird window).
 *
 * Idempotent: types are matched by (event, key); edited types are left as is.
 */
class TicketTypesSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);
        foreach (['north-batinah' => self::northBatinah(), 'demo' => self::demo()] as $slug => $events) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                continue;
            }
            $context->run($tenant, function () use ($events) {
                foreach ($events as $title => $types) {
                    $event = Event::where('title', $title)->first();
                    if (! $event) {
                        continue;
                    }
                    $event->ensureDefaultTicketTypes();
                    foreach ($types as $i => $type) {
                        $type = self::resolveDates($type, $event);
                        EventTicketType::firstOrCreate(['event_id' => $event->id, 'key' => $type['key']], $type + [
                            'admits' => 1, 'per_order_max' => 10, 'visibility' => EventTicketType::VISIBILITY_PUBLIC,
                            'sort' => $i + 1, 'is_active' => true,
                        ]);
                    }
                }
            });
        }
    }

    /** `sales_end_days_before_start` → a sales window relative to the event. */
    private static function resolveDates(array $type, Event $event): array
    {
        if (isset($type['sales_end_days_before_start'])) {
            $type['sales_end'] = $event->start_time?->copy()->subDays((int) $type['sales_end_days_before_start'])->format('Y-m-d H:i:s');
            unset($type['sales_end_days_before_start']);
        }

        return $type;
    }

    /** Prices in minor units (OMR × 1000). Everything except General is a placeholder. */
    public static function northBatinah(): array
    {
        return [
            'Sohar Festival 2026–27' => [
                ['key' => 'family', 'category' => 'family', 'price_minor' => 1500, 'admits' => 4, 'per_order_max' => 5,
                    'name' => ['en' => 'Family (4 people)', 'ar' => 'تذكرة عائلية'],
                    'description' => ['en' => 'Admits up to 4 people of one family.', 'ar' => 'تدخل بها عائلة واحدة حتى أربعة أشخاص.']],
                ['key' => 'child', 'category' => 'child', 'price_minor' => 250, 'max_age' => 11,
                    'name' => ['en' => 'Child (under 12)', 'ar' => 'تذكرة طفل'],
                    'description' => ['en' => 'For children under 12.', 'ar' => 'للأطفال دون 12 سنة.']],
                ['key' => 'senior', 'category' => 'senior', 'price_minor' => 250, 'min_age' => 60, 'requires_id' => true,
                    'name' => ['en' => 'Senior (60+)', 'ar' => 'تذكرة كبار السن'],
                    'description' => ['en' => 'For visitors aged 60 and over. Bring an ID card.', 'ar' => 'للزوار من عمر 60 سنة فأكثر، ويُطلب إبراز البطاقة الشخصية.']],
                ['key' => 'vip', 'category' => 'vip', 'price_minor' => 2000, 'per_order_max' => 6,
                    'name' => ['en' => 'VIP', 'ar' => 'كبار الشخصيات'],
                    'description' => ['en' => 'Entry through VIP Gate C.', 'ar' => 'الدخول من بوابة كبار الشخصيات ج.']],
            ],
        ];
    }

    public static function demo(): array
    {
        return [
            'Opening Night Concert' => [
                ['key' => 'vip', 'category' => 'vip', 'price_minor' => 12000, 'capacity' => 50, 'per_order_max' => 4,
                    'name' => ['en' => 'VIP', 'ar' => 'كبار الشخصيات'],
                    'description' => ['en' => 'Front seats and the VIP lounge.', 'ar' => 'مقاعد أمامية وصالة كبار الشخصيات.']],
                ['key' => 'student', 'category' => 'student', 'price_minor' => 3500, 'requires_id' => true,
                    'name' => ['en' => 'Student', 'ar' => 'تذكرة طالب'],
                    'description' => ['en' => 'A valid student ID is checked at the gate.', 'ar' => 'تُطلب بطاقة طالب سارية عند البوابة.']],
                ['key' => 'group', 'category' => 'group', 'price_minor' => 20000, 'admits' => 5, 'per_order_max' => 4,
                    'name' => ['en' => 'Group of 5', 'ar' => 'مجموعة من 5 أشخاص'],
                    'description' => ['en' => 'One ticket admits 5 people.', 'ar' => 'تدخل بالتذكرة الواحدة خمسة أشخاص.']],
                ['key' => 'door', 'category' => 'other', 'price_minor' => 6000, 'visibility' => EventTicketType::VISIBILITY_BOX_OFFICE,
                    'name' => ['en' => 'Door ticket', 'ar' => 'تذكرة الشباك'],
                    'description' => ['en' => 'Sold at the gate only.', 'ar' => 'تُباع عند البوابة فقط.']],
            ],
            'Expo Day Pass' => [
                ['key' => 'child', 'category' => 'child', 'price_minor' => 0, 'max_age' => 11,
                    'name' => ['en' => 'Child (under 12)', 'ar' => 'تذكرة طفل'],
                    'description' => ['en' => 'Free for children under 12.', 'ar' => 'مجانية للأطفال دون 12 سنة.']],
                ['key' => 'family', 'category' => 'family', 'price_minor' => 6000, 'admits' => 4, 'per_order_max' => 3,
                    'name' => ['en' => 'Family (4 people)', 'ar' => 'تذكرة عائلية'],
                    'description' => ['en' => 'Admits up to 4 people of one family.', 'ar' => 'تدخل بها عائلة واحدة حتى أربعة أشخاص.']],
                ['key' => 'senior', 'category' => 'senior', 'price_minor' => 1000, 'min_age' => 60, 'requires_id' => true,
                    'name' => ['en' => 'Senior (60+)', 'ar' => 'تذكرة كبار السن'],
                    'description' => ['en' => 'For visitors aged 60 and over. Bring an ID card.', 'ar' => 'للزوار من عمر 60 سنة فأكثر، ويُطلب إبراز البطاقة الشخصية.']],
                ['key' => 'early_bird', 'category' => 'other', 'price_minor' => 1500, 'sales_end_days_before_start' => 1,
                    'name' => ['en' => 'Early bird', 'ar' => 'حجز مبكر'],
                    'description' => ['en' => 'On sale until the day before the expo opens.', 'ar' => 'متاحة حتى اليوم السابق لافتتاح المعرض.']],
            ],
            'Magic & Illusions Show' => [
                ['key' => 'child', 'category' => 'child', 'price_minor' => 2000, 'max_age' => 11,
                    'name' => ['en' => 'Child (under 12)', 'ar' => 'تذكرة طفل'],
                    'description' => ['en' => 'For children under 12.', 'ar' => 'للأطفال دون 12 سنة.']],
            ],
        ];
    }
}

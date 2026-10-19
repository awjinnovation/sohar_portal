<?php

namespace Tests\Feature\Ai;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Test;

/** Concierge tools find_places / search_content and place / content cards (docs/places_content_contract.md §5). */
class ConciergePlacesTest extends AiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->modules($this->tenant, true, true);
    }

    private function modules(Tenant $tenant, bool $places, bool $content): void
    {
        $tenant->putSetting('modules.places', $places)->putSetting('modules.content', $content)->save();
        $tenant->refresh();
    }

    private function fort(?Tenant $tenant = null, array $attrs = []): Place
    {
        return $this->inTenant($tenant ?? $this->tenant, function () use ($attrs) {
            $cat = PlaceCategory::firstOrCreate(['key' => 'forts'], ['name' => ['en' => 'Forts', 'ar' => 'قلاع'], 'icon' => 'fort', 'color' => '#8D5B3A']);

            return Place::create(array_merge([
                'category_id' => $cat->id, 'slug' => 'shinas-fort', 'name' => ['en' => 'Shinas Fort', 'ar' => 'قلعة شناص'],
                'summary' => ['en' => 'Fort in the Wilayat of Shinas.', 'ar' => 'قلعة في ولاية شناص.'],
                'wilayat' => ['en' => 'Shinas', 'ar' => 'شناص'], 'latitude' => 24.74421, 'longitude' => 56.46637, 'status' => 'published',
            ], $attrs));
        });
    }

    private function service(?Tenant $tenant = null): ContentItem
    {
        return $this->inTenant($tenant ?? $this->tenant, function () {
            $col = ContentCollection::create(['key' => 'services', 'name' => ['en' => 'Visitor services', 'ar' => 'خدمات الزوار'], 'layout' => 'list', 'is_active' => true]);

            return ContentItem::create(['collection_id' => $col->id, 'slug' => 'lost-and-found', 'title' => ['en' => 'Lost and found', 'ar' => 'المفقودات'],
                'body' => ['en' => '<p>At the information desk.</p>'], 'link_url' => 'https://example.om/lost', 'status' => 'published']);
        });
    }

    #[Test]
    public function find_places_returns_tenant_places_and_builds_place_cards(): void
    {
        $fort = $this->fort();
        $this->fort(null, ['slug' => 'draft', 'name' => ['en' => 'Draft Fort', 'ar' => 'مسودة'], 'status' => 'draft']);
        $other = $this->makeTenant('other', ['modules' => ['places' => true]]);
        $foreign = $this->fort($other, ['slug' => 'foreign', 'name' => ['en' => 'Foreign Fort', 'ar' => 'قلعة أجنبية']]);

        $this->fakeOpenAi([
            $this->toolResponse([['find_places', ['query' => 'Shinas', 'category' => null, 'near' => null]]]),
            $this->textResponse("In Shinas you can visit Shinas Fort.\n<<<META {\"cards\":[{\"type\":\"place\",\"id\":{$fort->id}},{\"type\":\"place\",\"id\":{$foreign->id}}],\"suggestions\":[]}>>>"),
        ]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'What can I visit in Shinas?', 'locale' => 'en'], $this->guestHeaders())->assertOk();

        $first = $this->sent(0);
        $this->assertContains('find_places', array_column($first['tools'], 'name'));
        $this->assertContains('search_content', array_column($first['tools'], 'name'));
        $output = collect($this->sent(1)['input'])->firstWhere('type', 'function_call_output')['output'];
        $this->assertStringContainsString('Shinas Fort', $output);
        $this->assertStringNotContainsString('Draft Fort', $output);
        $this->assertStringNotContainsString('Foreign Fort', $output);

        $cards = $res->json('data.message.cards');
        $this->assertCount(1, $cards);   // the other tenant's place is dropped
        $this->assertSame(['type' => 'place', 'id' => $fort->id, 'name' => 'Shinas Fort', 'category' => 'Forts', 'category_key' => 'forts',
            'icon' => 'fort', 'color' => '#8D5B3A', 'cover_url' => null, 'latitude' => 24.74421, 'longitude' => 56.46637], $cards[0]);
    }

    #[Test]
    public function search_content_builds_content_cards_and_falls_back_to_mentions(): void
    {
        $item = $this->service();
        $this->fakeOpenAi([
            $this->toolResponse([['search_content', ['query' => 'lost', 'collection' => null]]]),
            // No META: cards come from tool results named in the answer.
            $this->textResponse('Lost and found is at the information desk.'),
        ]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'I lost my bag', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $output = collect($this->sent(1)['input'])->firstWhere('type', 'function_call_output')['output'];
        $this->assertStringContainsString('At the information desk.', $output);
        $this->assertSame([['type' => 'content', 'id' => $item->id, 'collection_key' => 'services', 'title' => 'Lost and found',
            'image_url' => null, 'link_url' => 'https://example.om/lost']], $res->json('data.message.cards'));
    }

    #[Test]
    public function tools_and_cards_are_gated_by_the_modules(): void
    {
        $fort = $this->fort();
        $item = $this->service();
        $this->modules($this->tenant, false, false);
        $this->fakeOpenAi([
            $this->textResponse("Hi.\n<<<META {\"cards\":[{\"type\":\"place\",\"id\":{$fort->id}},{\"type\":\"content\",\"id\":{$item->id}}],\"suggestions\":[]}>>>"),
        ]);
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Hi', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $names = array_column($this->sent(0)['tools'], 'name');
        $this->assertNotContains('find_places', $names);
        $this->assertNotContains('search_content', $names);
        $this->assertSame([], $res->json('data.message.cards'));
    }

    #[Test]
    public function near_sorts_places_by_distance(): void
    {
        $this->fort();
        $this->fort(null, ['slug' => 'suwaiq', 'name' => ['en' => 'Al Suwaiq Fort', 'ar' => 'حصن السويق'], 'latitude' => 23.84915, 'longitude' => 57.43970]);
        $tools = new \App\Services\Ai\Concierge\ConciergeTools($this->tenant, new \App\Services\Ai\EventFacts($this->tenant, 'en'));
        $out = $tools->execute('find_places', ['query' => null, 'category' => 'forts', 'near' => '23.85,57.44']);
        $this->assertSame(['Al Suwaiq Fort', 'Shinas Fort'], array_column($out['places'], 'name'));
        $this->assertLessThan(1, $out['places'][0]['distance_km']);
    }

    #[Test]
    public function the_reply_language_follows_the_message_then_the_locale(): void
    {
        $fort = $this->fort();
        $this->fakeOpenAi([
            $this->toolResponse([['find_places', ['query' => 'Shinas', 'category' => null, 'near' => null]]]),
            $this->textResponse("You can visit Shinas Fort.\n<<<META {\"cards\":[{\"type\":\"place\",\"id\":{$fort->id}}],\"suggestions\":[]}>>>"),
        ]);
        // English question while the app (or a missing Accept-Language) says Arabic.
        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'What can I visit in Shinas?', 'locale' => 'ar'], $this->guestHeaders())->assertOk();
        $this->assertStringContainsString('latest message is in English: write this whole reply', $this->sent(0)['instructions']);
        $output = collect($this->sent(1)['input'])->firstWhere('type', 'function_call_output')['output'];
        $this->assertStringContainsString('Shinas Fort', $output);          // tool data in English
        $this->assertStringNotContainsString('قلعة شناص', $output);
        $this->assertSame('Shinas Fort', $res->json('data.message.cards.0.name'));
        $this->assertSame("What's on tonight?", $res->json('data.message.suggestions.0'));   // tenant defaults in English

        $this->fakeOpenAi([$this->textResponse('أهلًا وسهلًا.')]);
        $this->postJson('/api/v1/ai/chat', ['message' => 'ماذا أزور في شناص؟', 'locale' => 'en'], $this->guestHeaders())->assertOk();
        $this->assertStringContainsString('latest message is in Arabic', $this->sent(0)['instructions']);

        // No letters at all: the locale field decides.
        $this->assertSame('en', \App\Services\Ai\Concierge\ConciergeService::replyLocale('2026 ?', 'en'));
        $this->assertSame('ar', \App\Services\Ai\Concierge\ConciergeService::replyLocale('Sohar قلعة صحار ماذا', 'en'));
    }
}

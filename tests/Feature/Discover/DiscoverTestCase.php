<?php

namespace Tests\Feature\Discover;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Place;
use App\Models\PlaceCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

abstract class DiscoverTestCase extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableModules($this->tenant);
    }

    protected function enableModules(Tenant $tenant, bool $places = true, bool $content = true): Tenant
    {
        $tenant->putSetting('modules.places', $places)->putSetting('modules.content', $content)->save();

        return $tenant->refresh();
    }

    protected function category(?Tenant $tenant = null, array $attrs = []): PlaceCategory
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => PlaceCategory::create(array_merge([
            'key' => 'forts', 'name' => ['en' => 'Forts', 'ar' => 'قلاع'], 'icon' => 'fort', 'color' => '#8D5B3A', 'sort' => 1, 'is_active' => true,
        ], $attrs)));
    }

    protected function place(?Tenant $tenant = null, array $attrs = []): Place
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => Place::create(array_merge([
            'slug' => 'sohar-fort', 'name' => ['en' => 'Sohar Fort', 'ar' => 'قلعة صحار'],
            'summary' => ['en' => 'Historic fort', 'ar' => 'قلعة تاريخية'],
            'latitude' => 24.36203, 'longitude' => 56.74929, 'wilayat' => ['en' => 'Sohar', 'ar' => 'صحار'],
            'status' => 'published', 'sort' => 1,
        ], $attrs)));
    }

    protected function collection(?Tenant $tenant = null, array $attrs = []): ContentCollection
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => ContentCollection::create(array_merge([
            'key' => 'services', 'name' => ['en' => 'Visitor services', 'ar' => 'خدمات الزوار'], 'layout' => 'list',
            'icon' => 'support_agent', 'show_in_discover' => true, 'sort' => 1, 'is_active' => true,
        ], $attrs)));
    }

    protected function item(ContentCollection $collection, array $attrs = []): ContentItem
    {
        return $this->inTenant(Tenant::find($collection->tenant_id), fn () => ContentItem::create(array_merge([
            'collection_id' => $collection->id, 'slug' => 'first-aid', 'title' => ['en' => 'First aid', 'ar' => 'الإسعافات الأولية'],
            'subtitle' => ['en' => 'Near gate A', 'ar' => 'قرب البوابة أ'], 'body' => ['en' => '<p>Open daily.</p>', 'ar' => '<p>مفتوحة يوميًا.</p>'],
            'status' => 'published', 'sort' => 1,
        ], $attrs)));
    }

    protected function page(?Tenant $tenant = null, array $attrs = []): ContentPage
    {
        return $this->inTenant($tenant ?? $this->tenant, fn () => ContentPage::create(array_merge([
            'slug' => 'about', 'title' => ['en' => 'About', 'ar' => 'عن المحافظة'], 'body' => ['en' => '<p>Hello</p>', 'ar' => '<p>مرحبًا</p>'],
            'status' => 'published', 'show_in_more' => true, 'sort' => 1,
        ], $attrs)));
    }
}

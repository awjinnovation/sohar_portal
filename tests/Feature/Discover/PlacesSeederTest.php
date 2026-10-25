<?php

namespace Tests\Feature\Discover;

use App\Models\ContentCollection;
use App\Models\ContentItem;
use App\Models\ContentPage;
use App\Models\Place;
use Database\Seeders\PlacesContentSeeder;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

class PlacesSeederTest extends DiscoverTestCase
{
    #[Test]
    public function seeding_is_idempotent_and_keeps_partners_empty(): void
    {
        Storage::fake('public');
        $run = fn () => $this->inTenant($this->tenant, fn () => (new PlacesContentSeeder)->run());
        $run();
        $counts = [Place::count(), ContentItem::count(), ContentPage::count(), ContentCollection::count()];
        $this->assertGreaterThanOrEqual(10, $counts[0]);

        Place::where('slug', 'sohar-fort')->first()->update(['summary' => ['en' => 'Edited', 'ar' => 'معدّل']]);
        $run();
        $this->assertSame($counts, [Place::count(), ContentItem::count(), ContentPage::count(), ContentCollection::count()]);
        $this->assertSame('Edited', Place::where('slug', 'sohar-fort')->first()->summary['en']);   // admin edits survive

        $this->assertSame(0, ContentCollection::where('key', 'partners')->first()->items()->count());
        $fort = Place::where('slug', 'sohar-fort')->first();
        $this->assertCount(1, $fort->getMedia('cover'));
        $this->assertNull(Place::where('slug', 'liwa-fort')->first()->getFirstMedia('cover'));
        // Rule D: unknown hours / fees stay empty for real places.
        $this->assertSame(0, Place::whereNotNull('opening_hours')->orWhereNotNull('entry_fee_minor')->count());
    }
}

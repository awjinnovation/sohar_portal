<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\MapLocation;
use App\Models\Notification;
use App\Models\PrivacyPolicy;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\TermsAndCondition;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Closure;
use Database\Seeders\DemoTenantSeeder;
use Database\Seeders\LegalTemplate;
use Database\Seeders\NorthBatinahEventsSeeder;
use Database\Seeders\NorthBatinahVendorsSeeder;
use Database\Seeders\PlacesContentSeeder;
use Database\Seeders\PrivacyPolicySeeder;
use Database\Seeders\TenantSeeder;
use Database\Seeders\TermsAndConditionSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bring the Arabic texts of rows created by the seeders up to the seeders'
 * current wording, without re-seeding:
 *
 *   php artisan content:sync-arabic --dry-run
 *   php artisan content:sync-arabic --tenant=demo
 *
 * The texts come from the seeder classes themselves (TenantSeeder::northBatinah()
 * / demo(), NorthBatinahEventsSeeder::content(), DemoTenantSeeder::content(),
 * NorthBatinahVendorsSeeder::VENDORS, the legal seeders' arabicTemplate()).
 * Rows are matched by their English natural key (slug, name, title); rows
 * that no longer exist are skipped.
 *
 * Only Arabic fields of existing rows are written: no row is inserted or
 * deleted, English fields are left alone, and gates, devices, scans, tickets,
 * wallets, payments and every other transactional table are never touched.
 * Idempotent: a second run reports 0 changes.
 */
class SyncArabicContent extends Command
{
    protected $signature = 'content:sync-arabic
        {--dry-run : List the changes without writing them}
        {--tenant=* : Only these tenant slugs (default: north-batinah and demo)}';

    protected $description = 'Update the Arabic texts of seeded rows to the current seeder wording';

    /** @var array<string, array{rows: int, fields: int}> per "tenant · table" */
    private array $stats = [];

    private int $fields = 0;

    private int $rows = 0;

    private int $missing = 0;

    private bool $dryRun = false;

    public function handle(TenantContext $context): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->stats = [];
        $this->fields = $this->rows = $this->missing = 0;

        $plans = [
            'north-batinah' => fn (Tenant $t) => $this->northBatinahTargets($t),
            'demo' => fn (Tenant $t) => $this->demoTargets($t),
        ];
        $definitions = ['north-batinah' => TenantSeeder::northBatinah(...), 'demo' => TenantSeeder::demo(...)];

        $only = array_filter((array) $this->option('tenant'));
        if ($unknown = array_diff($only, array_keys($plans))) {
            $this->error('No seeded content for tenant(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        foreach ($only ?: array_keys($plans) as $slug) {
            $tenant = Tenant::findBySlug($slug);
            if (! $tenant) {
                $this->warn("Tenant {$slug} not found, skipped.");

                continue;
            }

            $context->run($tenant, function (Tenant $tenant) use ($slug, $plans, $definitions) {
                $sync = function () use ($tenant, $slug, $plans, $definitions) {
                    $this->syncTenantRow($tenant, $definitions[$slug]());
                    foreach ($plans[$slug]($tenant) as [$table, $key, $find, $values]) {
                        $this->syncRow($slug, $table, $key, $find, $values);
                    }
                };
                $this->dryRun ? $sync() : DB::transaction($sync);
            });
        }

        if ($this->stats) {
            $this->newLine();
            $this->table(['Tenant · table', 'Rows', 'Fields'], collect($this->stats)
                ->map(fn ($s, $k) => [$k, $s['rows'], $s['fields']])->values()->all());
        }
        if ($this->missing) {
            $this->line("{$this->missing} seeded row(s) not found in the database (skipped).");
        }
        $this->info(($this->dryRun ? 'Dry run: ' : '')
            ."{$this->fields} changes in {$this->rows} rows"
            .($this->dryRun ? ' would be written.' : '.'));

        return self::SUCCESS;
    }

    /** @return list<array{string, string, Closure, array<string, string>}> */
    private function northBatinahTargets(Tenant $tenant): array
    {
        $c = NorthBatinahEventsSeeder::content();
        $targets = [];

        foreach ($c['categories'] as $cat) {
            $targets[] = ['categories', $cat['name'], fn () => Category::where('name', $cat['name'])->first(),
                ['name_ar' => $cat['name_ar'], 'description_ar' => $cat['d_ar']]];
        }
        foreach ($c['map'] as $m) {
            $targets[] = ['map_locations', $m['n'], fn () => MapLocation::where('name', $m['n'])->first(),
                ['name_ar' => $m['n_ar'], 'description_ar' => $m['d_ar']]];
        }
        foreach ($c['events'] as $e) {
            $benefits = NorthBatinahEventsSeeder::PRICING_BENEFITS[$e['price'] > 0 ? 'paid' : 'free'][1];
            $targets[] = ['events', $e['t'], fn () => Event::where('title', $e['t'])->first(), [
                'title_ar' => $e['t_ar'], 'description_ar' => $e['d_ar'],
                'location_ar' => $e['venue'][1], 'organizer_name_ar' => $e['org'][1],
                'pricing.0.benefits_ar' => $benefits,
            ]];
            foreach ($e['tags'] as [$tag, $tagAr]) {
                $targets[] = ['event_tags', "{$e['t']} / {$tag}", fn () => EventTag::where('tag', $tag)
                    ->whereIn('event_id', Event::where('title', $e['t'])->select('id'))->first(), ['tag_ar' => $tagAr]];
            }
        }
        foreach ($c['restaurants'] as $r) {
            $values = ['name_ar' => $r['n_ar'], 'description_ar' => $r['d_ar'], 'cuisine_ar' => $r['cui_ar'],
                'location_ar' => $c['restaurant_location'][1]];
            foreach ($c['stall_hours'] as $i => $hours) {
                $values["opening_hours.$i.day_ar"] = $hours['day_ar'];
            }
            $targets[] = ['restaurants', $r['n'], fn () => Restaurant::where('name', $r['n'])->first(), $values];
        }
        // Unified locations table (query builder; tenant column only on newer schemas).
        $locationsHaveTenant = Schema::hasColumn('locations', 'tenant_id');
        foreach ($c['locations'] as $l) {
            $targets[] = ['locations', $l['name'], fn () => DB::table('locations')->where('name', $l['name'])
                ->when($locationsHaveTenant, fn ($q) => $q->where('tenant_id', $tenant->id))->first(),
                ['name_ar' => $l['name_ar'], 'description_ar' => $l['name_ar'], 'address_ar' => $l['addr_ar']]];
        }
        foreach ($c['announcements'] as $a) {
            $targets[] = ['announcements', $a['t'], fn () => Announcement::where('title', $a['t'])->first(),
                ['title_ar' => $a['t_ar'], 'content_ar' => $a['c_ar']]];
        }
        $visitorId = User::where('phone_number', $c['demo_visitor_phone'])->value('id');
        foreach ($c['notifications'] as $n) {
            $targets[] = ['notifications', $n['t'], fn () => $visitorId
                ? Notification::where('user_id', $visitorId)->where('title', $n['t'])->first() : null,
                ['title_ar' => $n['t_ar'], 'body_ar' => $n['b_ar']]];
        }
        foreach (NorthBatinahVendorsSeeder::VENDORS as $v) {
            $targets[] = $this->vendorTarget($v['en'], $v['ar']);
        }
        $targets[] = ['terms_and_conditions', TermsAndConditionSeeder::TITLE,
            fn () => TermsAndCondition::where('title', TermsAndConditionSeeder::TITLE)->first(), [
                'title_ar' => TermsAndConditionSeeder::TITLE_AR,
                'content_ar' => LegalTemplate::fill(TermsAndConditionSeeder::arabicTemplate(), $tenant),
            ]];
        $targets[] = ['privacy_policies', PrivacyPolicySeeder::TITLE,
            fn () => PrivacyPolicy::where('title', PrivacyPolicySeeder::TITLE)->first(), [
                'title_ar' => PrivacyPolicySeeder::TITLE_AR,
                'content_ar' => LegalTemplate::fill(PrivacyPolicySeeder::arabicTemplate(), $tenant),
            ]];

        return array_merge($targets, $this->placesTargets('north-batinah'));
    }

    /**
     * Places, place categories, content collections / items and pages
     * (PlacesContentSeeder), matched by key / slug; JSON paths `<field>.ar`.
     *
     * @return list<array{string, string, Closure, array<string, string>}>
     */
    private function placesTargets(string $slug): array
    {
        if (! Schema::hasTable('places')) {
            return [];
        }
        $defs = PlacesContentSeeder::definitions($slug);
        $ar = function (array $def, array $fields, array $html = []): array {
            $out = [];
            foreach ($fields as $f) {
                if (isset($def[$f]['ar'])) {
                    $out["$f.ar"] = in_array($f, $html, true) ? \App\Support\HtmlSanitizer::clean($def[$f]['ar']) : $def[$f]['ar'];
                }
            }

            return $out;
        };
        $targets = [];
        foreach ($defs['categories'] as $c) {
            $targets[] = ['place_categories', $c['key'], fn () => \App\Models\PlaceCategory::where('key', $c['key'])->first(), $ar($c, ['name'])];
        }
        foreach ($defs['places'] as $p) {
            $targets[] = ['places', $p['slug'], fn () => \App\Models\Place::where('slug', $p['slug'])->first(),
                $ar($p, ['name', 'summary', 'description', 'address', 'wilayat', 'opening_hours'], ['description'])];
        }
        foreach ($defs['collections'] as $c) {
            $targets[] = ['content_collections', $c['key'], fn () => \App\Models\ContentCollection::where('key', $c['key'])->first(), $ar($c, ['name', 'description'])];
            foreach ($c['items'] as $i) {
                $targets[] = ['content_items', $c['key'].' / '.$i['slug'], fn () => \App\Models\ContentItem::where('slug', $i['slug'])
                    ->whereIn('collection_id', \App\Models\ContentCollection::where('key', $c['key'])->select('id'))->first(),
                    $ar($i, ['title', 'subtitle', 'body'], ['body'])];
            }
        }
        foreach ($defs['pages'] as $pg) {
            $targets[] = ['content_pages', $pg['slug'], fn () => \App\Models\ContentPage::where('slug', $pg['slug'])->first(), $ar($pg, ['title', 'body'], ['body'])];
        }

        return $targets;
    }

    /** @return list<array{string, string, Closure, array<string, string>}> */
    private function demoTargets(Tenant $tenant): array
    {
        $c = DemoTenantSeeder::content();
        $text = $c['event_text'];
        $targets = [];

        foreach ($c['categories'] as $cat) {
            $targets[] = ['categories', $cat['name'], fn () => Category::where('name', $cat['name'])->first(),
                ['name_ar' => $cat['name_ar'], 'description_ar' => $cat['description_ar']]];
        }
        foreach ($c['events'] as [$title, $titleAr]) {
            $targets[] = ['events', $title, fn () => Event::where('title', $title)->first(), [
                'title_ar' => $titleAr, 'description_ar' => $text['description_ar']($titleAr),
                'location_ar' => $text['location_ar'], 'organizer_name_ar' => $text['organizer_name_ar'],
            ]];
        }
        foreach ($c['announcements'] as $a) {
            $targets[] = ['announcements', $a['title'], fn () => Announcement::where('title', $a['title'])->first(),
                ['title_ar' => $a['title_ar'], 'content_ar' => $a['content_ar']]];
        }
        foreach ($c['vendors'] as $v) {
            $targets[] = $this->vendorTarget($v['en'], $v['ar']);
        }
        foreach (['terms_and_conditions' => [TermsAndCondition::class, $c['terms']], 'privacy_policies' => [PrivacyPolicy::class, $c['privacy']]] as $table => [$model, $page]) {
            $targets[] = [$table, $page['title'], fn () => $model::where('title', $page['title'])->first(),
                ['title_ar' => $page['title_ar'], 'content_ar' => $page['content_ar']]];
        }

        return array_merge($targets, $this->placesTargets('demo'));
    }

    /** Vendors keep their name as {en, ar} JSON; matched like NorthBatinahVendorsSeeder::vendor(). */
    private function vendorTarget(string $en, string $ar): array
    {
        return ['vendors', $en, fn () => Vendor::all()->first(fn (Vendor $v) => ($v->name['en'] ?? null) === $en),
            ['name.ar' => $ar]];
    }

    /** Tenant name + every Arabic string of the seeded settings document, by path. */
    private function syncTenantRow(Tenant $tenant, array $definition): void
    {
        $values = ['name.ar' => $definition['name']['ar']];
        foreach ($this->arabicLeaves($definition['settings']) as $path => $text) {
            $values["settings.$path"] = $text;
        }

        $this->syncRow($tenant->slug, 'tenants', $tenant->slug, fn () => $tenant, $values, function (Tenant $tenant) {
            $tenant->save(); // Tenant::saved forgets tenant_by_slug_* and tenant_config_* caches
            Cache::forget(Tenant::cacheKey($tenant->slug));
            Cache::forget('tenant_config_'.$tenant->id);
        });
    }

    /** @return array<string, string> dotted path => Arabic text */
    private function arabicLeaves(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "$prefix.$key";
            if (is_array($value)) {
                $out += $this->arabicLeaves($value, $path);
            } elseif (is_string($value) && preg_match('/\p{Arabic}/u', $value)) {
                $out[$path] = $value;
            }
        }

        return $out;
    }

    /**
     * Compare and update one row. $values maps a column, or "column.json.path"
     * for array/JSON columns, to its Arabic text. A JSON path is only written
     * when it already holds a string, so no key is ever added.
     */
    private function syncRow(string $tenant, string $table, string $key, Closure $find, array $values, ?Closure $save = null): void
    {
        $row = $find();
        if (! $row) {
            $this->missing++;
            if ($this->output->isVerbose()) {
                $this->line("  [{$tenant}] {$table} «{$key}» not found, skipped");
            }

            return;
        }

        $isModel = $row instanceof Model;
        $updates = [];
        $changed = 0;
        foreach ($values as $field => $new) {
            [$column, $path] = array_pad(explode('.', $field, 2), 2, null);
            $current = $updates[$column] ?? ($isModel ? $row->getAttribute($column) : ($row->{$column} ?? null));

            if ($path === null) {
                if (! is_string($current) || $current === $new) {
                    continue;
                }
                $old = $current;
                $updates[$column] = $new;
            } else {
                $data = is_array($current) ? $current : (is_string($current) ? json_decode($current, true) : null);
                if (! is_array($data) || ! is_string($old = data_get($data, $path)) || $old === $new) {
                    continue;
                }
                data_set($data, $path, $new);
                $updates[$column] = $data;
            }

            $changed++;
            $this->line("  [{$tenant}] {$table} «".Str::limit($key, 40)."» {$field}: "
                .Str::limit($old, 50).' → '.Str::limit($new, 50));
        }

        if (! $changed) {
            return;
        }

        $this->fields += $changed;
        $this->rows++;
        $stat = $this->stats["{$tenant} · {$table}"] ?? ['rows' => 0, 'fields' => 0];
        $this->stats["{$tenant} · {$table}"] = ['rows' => $stat['rows'] + 1, 'fields' => $stat['fields'] + $changed];

        if ($this->dryRun) {
            return;
        }
        if ($isModel) {
            $row->forceFill($updates);
            $save ? $save($row) : $row->saveQuietly();
        } else {
            DB::table($table)->where('id', $row->id)->update(array_map(
                fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v,
                $updates,
            ) + (Schema::hasColumn($table, 'updated_at') ? ['updated_at' => now()] : []));
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\BoxOfficeOrder;
use App\View\TenantTheme;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Gate\GateTestCase;

/**
 * The customer pages (layouts/public: pay links sent by SMS, payment results,
 * tickets, events, places, legal) load only from this server, through the
 * CDN: fonts from public/fonts (php artisan fonts:self-host) and the icons
 * they use from public/fonts/bootstrap-icons (scripts/public-icons.py), not
 * Google Fonts or jsDelivr, which held up the first paint on phones.
 */
class PublicPageAssetsTest extends GateTestCase
{
    /** Same list as VIEW_PATHS in scripts/public-icons.py. */
    private const VIEW_PATHS = ['layouts/public.blade.php', 'partials', 'payment', 'public', 'visitor', 'components/admin/money.blade.php'];

    #[Test]
    public function a_pay_link_page_loads_its_fonts_and_icons_from_this_server(): void
    {
        $order = $this->inTenant($this->tenant, fn () => BoxOfficeOrder::create([
            'gate_id' => $this->makeGate()->id, 'reference' => 'BO-111111-0009', 'buyer_phone' => '96891234567',
            'booking_date' => now()->toDateString(), 'quantity' => 1, 'unit_price_minor' => 500, 'total_minor' => 500,
            'currency' => 'OMR', 'status' => 'pending', 'public_token' => str_repeat('c', 32),
            'expires_at' => now()->addMinutes(15), 'idempotency_key' => 'assets-1',
        ]));

        $html = $this->get(route('box-office.pay', ['token' => $order->public_token]))->assertOk()->getContent();

        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/fonts/cairo\.css\?v=\d+"#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/fonts/inter\.css\?v=\d+"#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*/fonts/bootstrap-icons/public\.css\?v=\d+"#', $html);
    }

    #[Test]
    public function every_curated_font_is_self_hosted(): void
    {
        foreach (array_keys(TenantTheme::FONTS) as $family) {
            $css = public_path('fonts/'.TenantTheme::fontSlug($family).'.css');
            $this->assertFileExists($css, "{$family}: run php artisan fonts:self-host --family=\"{$family}\"");
            preg_match_all('#url\(([^)]+)\)#', file_get_contents($css), $urls);
            $this->assertNotEmpty($urls[1], "{$family}: no font files in {$css}");
            foreach ($urls[1] as $url) {
                $this->assertStringStartsNotWith('http', $url, "{$family}: {$url} is not local");
                $this->assertFileExists(public_path('fonts/'.$url));
            }
        }

        // A font outside the curated list (no local copy) still comes from Google Fonts.
        $this->assertNull(TenantTheme::localFontCss(['Cairo', 'Some Other Font']));
    }

    #[Test]
    public function every_icon_the_public_views_use_is_in_the_icon_subset(): void
    {
        $css = file_get_contents(public_path('fonts/bootstrap-icons/public.css'));
        preg_match('#url\("([^"]+)"\)#', $css, $font);
        $this->assertFileExists(public_path('fonts/bootstrap-icons/'.$font[1]));

        $missing = [];
        foreach ($this->usedIcons() as $icon => $file) {
            if (! str_contains($css, ".bi-{$icon}::before")) {
                $missing[] = "bi-{$icon} ({$file})";
            }
        }

        $this->assertSame([], $missing, 'Icons missing from public/fonts/bootstrap-icons: run python3 scripts/public-icons.py <bootstrap-icons package> (see the script).');
    }

    /** @return array<string, string> icon name => first view using it (same rules as the script) */
    private function usedIcons(): array
    {
        $icons = [];
        foreach (self::VIEW_PATHS as $rel) {
            $path = resource_path('views/'.$rel);
            $files = is_file($path) ? [$path] : array_map(fn ($f) => $f->getPathname(), \Illuminate\Support\Facades\File::allFiles($path));
            foreach ($files as $file) {
                if (! str_ends_with($file, '.blade.php')) {
                    continue;
                }
                $text = file_get_contents($file);
                preg_match_all('/\bbi-([a-z0-9]+(?:-[a-z0-9]+)*)/', $text, $plain);
                preg_match_all('/\bbi-\{\{(.*?)\}\}/s', $text, $dynamic);
                $quoted = [];
                foreach ($dynamic[1] as $expr) {
                    preg_match_all('/[\'"]([a-z0-9]+(?:-[a-z0-9]+)*)[\'"]/', $expr, $q);
                    $quoted = array_merge($quoted, $q[1]);
                }
                foreach (array_merge($plain[1], $quoted) as $icon) {
                    $icons[$icon] ??= str_replace(resource_path('views/'), '', $file);
                }
            }
        }

        return $icons;
    }
}

<?php

namespace App\Support;

/**
 * Contrast guard for tenant brand colors (docs/design_system.md §2.3).
 *
 * Web port of the app's `color_guard.dart`. Tone shifting works in CIELAB:
 * HCT "tone" is exactly CIELAB L*, so shift amounts (in tone units) match the
 * app; hue/chroma are kept in CIE LCh(ab) instead of CAM16, and chroma is
 * reduced only when the darker/lighter color would leave the sRGB gamut.
 * Results are within a couple of RGB steps of the HCT reference values
 * (e.g. #2F8E80 → #107B6D-ish, #4F46E5 unchanged).
 *
 * All colors are "#RRGGBB" strings.
 */
class ColorGuard
{
    public const INK = '#111827';

    public const WHITE = '#FFFFFF';

    public const BLACK = '#000000';

    public const MAX_BRAND_TONE_SHIFT = 12.0;

    /** Normalize "#abc" / "abc" / "#AABBCC" → "#AABBCC", or null when invalid. */
    public static function normalize(mixed $hex): ?string
    {
        if (! is_string($hex)) {
            return null;
        }
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (preg_match('/^[0-9a-fA-F]{8}$/', $hex)) {
            $hex = substr($hex, 0, 6); // drop alpha
        }

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? '#'.strtoupper($hex) : null;
    }

    public static function valid(mixed $hex, string $fallback): string
    {
        return self::normalize($hex) ?? self::normalize($fallback) ?? '#000000';
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = self::valid($hex, '#000000');

        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    public static function hex(float $r, float $g, float $b): string
    {
        $c = fn ($v) => str_pad(dechex((int) max(0, min(255, round($v)))), 2, '0', STR_PAD_LEFT);

        return strtoupper('#'.$c($r).$c($g).$c($b));
    }

    /** "r, g, b" for rgba()/Bootstrap *-rgb variables. */
    public static function rgbString(string $hex): string
    {
        return implode(', ', self::rgb($hex));
    }

    private static function lin(float $c): float
    {
        $c /= 255;

        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function delin(float $c): float
    {
        $v = $c <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;

        return $v * 255;
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * self::lin($r) + 0.7152 * self::lin($g) + 0.0722 * self::lin($b);
    }

    /** WCAG 2.x contrast ratio. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Alpha-blend $fg at $alpha over $bg. */
    public static function blend(string $fg, string $bg, float $alpha): string
    {
        [$r1, $g1, $b1] = self::rgb($fg);
        [$r2, $g2, $b2] = self::rgb($bg);

        return self::hex($r1 * $alpha + $r2 * (1 - $alpha), $g1 * $alpha + $g2 * (1 - $alpha), $b1 * $alpha + $b2 * (1 - $alpha));
    }

    /** Best of white / ink / black on $bg (white preferred, then ink). */
    public static function bestOn(string $bg): string
    {
        $cw = self::contrast(self::WHITE, $bg);
        if ($cw >= 4.5) {
            return self::WHITE;
        }
        if (self::contrast(self::INK, $bg) >= 4.5) {
            return self::INK;
        }

        return self::contrast(self::BLACK, $bg) >= $cw ? self::BLACK : self::WHITE;
    }

    // ---- CIELAB (D65) ------------------------------------------------------

    /** @return array{0:float,1:float,2:float} L*, a*, b* */
    public static function lab(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($v) => self::lin($v), self::rgb($hex));
        $x = (0.4124564 * $r + 0.3575761 * $g + 0.1804375 * $b) / 0.95047;
        $y = (0.2126729 * $r + 0.7151522 * $g + 0.0721750 * $b) / 1.0;
        $z = (0.0193339 * $r + 0.1191920 * $g + 0.9503041 * $b) / 1.08883;
        $f = fn ($t) => $t > 216 / 24389 ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116;
        [$fx, $fy, $fz] = [$f($x), $f($y), $f($z)];

        return [116 * $fy - 16, 500 * ($fx - $fy), 200 * ($fy - $fz)];
    }

    /** @return array{0:float,1:float,2:float}|null linear-free sRGB 0..255 or null when out of gamut */
    private static function labToRgb(float $l, float $a, float $b, bool $clip = false): ?array
    {
        $fy = ($l + 16) / 116;
        $fx = $fy + $a / 500;
        $fz = $fy - $b / 200;
        $inv = fn ($t) => $t ** 3 > 216 / 24389 ? $t ** 3 : (116 * $t - 16) / (24389 / 27);
        $x = $inv($fx) * 0.95047;
        $y = $l > 8 ? $fy ** 3 : $l / (24389 / 27);
        $z = $inv($fz) * 1.08883;
        $rl = 3.2404542 * $x - 1.5371385 * $y - 0.4985314 * $z;
        $gl = -0.9692660 * $x + 1.8760108 * $y + 0.0415560 * $z;
        $bl = 0.0556434 * $x - 0.2040259 * $y + 1.0572252 * $z;
        $eps = 0.0005;
        foreach ([$rl, $gl, $bl] as $v) {
            if (! $clip && ($v < -$eps || $v > 1 + $eps)) {
                return null;
            }
        }

        return [self::delin(max(0, min(1, $rl))), self::delin(max(0, min(1, $gl))), self::delin(max(0, min(1, $bl)))];
    }

    /** Tone (L*) of a color, 0..100. */
    public static function tone(string $hex): float
    {
        return self::lab($hex)[0];
    }

    /**
     * Same hue as $hex at tone $tone. Chroma is kept when possible and reduced
     * (binary search) only to stay inside sRGB. $chromaScale lets dark-mode
     * tones use a softer chroma.
     */
    public static function withTone(string $hex, float $tone, float $chromaScale = 1.0): string
    {
        [, $a, $b] = self::lab($hex);
        $tone = max(0, min(100, $tone));
        $a *= $chromaScale;
        $b *= $chromaScale;
        if ($rgb = self::labToRgb($tone, $a, $b)) {
            return self::hex(...$rgb);
        }
        $lo = 0.0;
        $hi = 1.0;
        for ($i = 0; $i < 20; $i++) {
            $mid = ($lo + $hi) / 2;
            self::labToRgb($tone, $a * $mid, $b * $mid) ? $lo = $mid : $hi = $mid;
        }

        return self::hex(...self::labToRgb($tone, $a * $lo, $b * $lo, true));
    }

    /**
     * Darken $hex in 0.5 tone steps until every color returned by
     * $against($candidate) reaches $ratio. Null when it needs more than $maxShift.
     *
     * @param  callable(string):array<string>  $against
     */
    public static function toneShift(string $hex, callable $against, float $ratio = 4.5, float $maxShift = self::MAX_BRAND_TONE_SHIFT): ?string
    {
        $tone = self::tone($hex);
        for ($d = 0.0; $d <= $maxShift + 1e-9; $d += 0.5) {
            $cand = $d == 0.0 ? self::valid($hex, '#000000') : self::withTone($hex, $tone - $d);
            $ok = true;
            foreach ($against($cand) as $x) {
                if (self::contrast($cand, $x) < $ratio) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $cand;
            }
        }

        return null;
    }

    public static function soft(string $hex, string $over = self::WHITE, float $alpha = 0.10): string
    {
        return self::blend($hex, $over, $alpha);
    }

    /**
     * Light-mode guard for an action color (primary / secondary).
     *
     * @return array{fill:string,on:string,text:string,soft:string,step:int,shift:float}
     */
    public static function guardAction(string $seed, ?string $requestedOn = null, string $background = '#F7F8FA'): array
    {
        $seed = self::valid($seed, '#2F6FED');
        $requestedOn = self::normalize($requestedOn);
        $on0 = $requestedOn ?? self::WHITE;
        $soft = fn (string $c) => self::soft($c);

        if (self::contrast($on0, $seed) >= 4.5 && self::contrast($seed, $background) >= 4.5 && self::contrast($seed, $soft($seed)) >= 4.5) {
            return ['fill' => $seed, 'on' => $on0, 'text' => $seed, 'soft' => $soft($seed), 'step' => 0, 'shift' => 0.0];
        }

        $against = fn (string $c) => [self::WHITE, $background, $soft($c)];
        $shifted = self::toneShift($seed, $against);
        if ($shifted !== null && ($requestedOn === null || $requestedOn === self::WHITE)) {
            return ['fill' => $shifted, 'on' => self::WHITE, 'text' => $shifted, 'soft' => $soft($shifted), 'step' => 1,
                'shift' => round(self::tone($seed) - self::tone($shifted), 1)];
        }

        $text = self::toneShift($seed, $against, 4.5, 60) ?? self::INK;

        return ['fill' => $seed, 'on' => self::bestOn($seed), 'text' => $text, 'soft' => $soft($seed), 'step' => 2,
            'shift' => round(self::tone($seed) - self::tone($text), 1)];
    }

    /** Brand surfaces that always carry white text (header band, wallet card). */
    public static function guardOnWhite(string $hex): string
    {
        $hex = self::valid($hex, '#2F6FED');

        return self::toneShift($hex, fn () => [self::WHITE], 4.5, 40) ?? $hex;
    }

    /**
     * Dark-mode brand role (R3): tone 80 fill/text, tone 20 on-color,
     * soft = fill at 14% over the dark surface.
     *
     * @return array{fill:string,on:string,text:string,soft:string}
     */
    public static function darkRole(string $seed, string $surface = '#171A21'): array
    {
        $seed = self::valid($seed, '#2F6FED');
        $fill = self::withTone($seed, 80);
        $on = self::withTone($seed, 20);
        if (self::contrast($on, $fill) < 4.5) {
            $on = self::bestOn($fill);
        }

        return ['fill' => $fill, 'on' => $on, 'text' => $fill, 'soft' => self::blend($fill, $surface, 0.14)];
    }
}

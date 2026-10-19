<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\ArabicTextFixer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Inputs are written with tokens ({c} = Latin comma, {s} = semicolon,
 * {q} = question mark, {em} = em dash, {en} = en dash, {_} = a space before
 * punctuation, {b} = exclamation mark) so the faulty Arabic never appears as a literal in source
 * and the Arabic style linter stays clean.
 */
class ArabicTextFixerTest extends TestCase
{
    private static function raw(string $text): string
    {
        return strtr($text, ['{c}' => ',', '{s}' => ';', '{q}' => '?', '{em}' => '—', '{en}' => '–', '{_}' => ' ', '{b}' => '!']);
    }

    public static function fixes(): array
    {
        return [
            'comma, semicolon, question mark' => ['مرحبًا{c} هل تبحث عن فعالية{q} الحفل{s} المعرض.', 'مرحبًا، هل تبحث عن فعالية؟ الحفل؛ المعرض.'],
            'comma after a closing quote' => ['شاهد «حفل الافتتاح»{c} ثم العشاء', 'شاهد «حفل الافتتاح»، ثم العشاء'],
            'missing space after the comma' => ['الحفل{c}المعرض', 'الحفل، المعرض'],
            'em dash, spaced or not' => ['الحفل {em} مجاني{em}يبدأ 16:00', 'الحفل، مجاني، يبدأ 16:00'],
            'spaced en dash between words' => ['الدخول {en} مجاني', 'الدخول، مجاني'],
            'space before punctuation' => ['الحفل{_}، مجاني{_}. هل الدخول مجاني{_}؟ نعم{_}{b} الوقت{_}: 16:00', 'الحفل، مجاني. هل الدخول مجاني؟ نعم{b} الوقت: 16:00'],
            'comma with a space before it' => ['الحفل{_}{c} المعرض', 'الحفل، المعرض'],
            'list lines' => ["الفعاليات\n- الحفل{c} مجاني\n- المعرض{c} OMR 2.500", "الفعاليات\n- الحفل، مجاني\n- المعرض، OMR 2.500"],
            'arabic-indic digits' => ['يبدأ ١٦:٠٠ والسعر OMR ٢.٥٠٠', 'يبدأ 16:00 والسعر OMR 2.500'],
            'digits with a thousands comma' => ['حضر 1{c}000 زائر', 'حضر 1{c}000 زائر'],
            'time range en dash' => ['من 19:00–20:00', 'من 19:00–20:00'],
        ];
    }

    #[Test]
    #[DataProvider('fixes')]
    public function it_fixes_arabic_punctuation(string $input, string $expected): void
    {
        $this->assertSame(self::raw($expected), ArabicTextFixer::fix(self::raw($input)));
    }

    #[Test]
    public function it_leaves_english_code_urls_numbers_and_json_alone(): void
    {
        foreach ([
            'Hello, world; how are you? Gates open — come early – really!',
            'Prices: 1,000.500 OMR; 2,5 is not 2.5',
            'See https://example.com/path?a=1,2&b=3;c=4 now.',
            'if (a, b) { return x ? y : z; }',
            '{"title":"Concert","tags":["a","b"],"n":1}',
            "line one ,\nline two ;",
        ] as $text) {
            $this->assertSame($text, ArabicTextFixer::fix($text));
        }
    }

    #[Test]
    public function arabic_inside_english_or_json_is_not_rewritten(): void
    {
        $json = json_encode(['title' => 'حفل', 'venue' => 'صحار', 'n' => 1], JSON_UNESCAPED_UNICODE);
        $this->assertSame($json, ArabicTextFixer::fix($json));
        $this->assertSame(self::raw('Visit صحار{c} then Muscat'), ArabicTextFixer::fix(self::raw('Visit صحار{c} then Muscat')));
        $this->assertSame(self::raw('صحار{q}q=1'), ArabicTextFixer::fix(self::raw('صحار{q}q=1')));
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        foreach (self::fixes() as [$input]) {
            $once = ArabicTextFixer::fix(self::raw($input));
            $this->assertSame($once, ArabicTextFixer::fix($once));
        }
    }

    #[Test]
    public function stream_deltas_map_a_comma_split_across_a_chunk_boundary(): void
    {
        $deltas = [self::raw('مرحبًا'), self::raw('{c} كيف'), self::raw(' حالك'), self::raw('{q}'), self::raw(' 1{c}000')];
        $carry = '';
        $out = '';
        foreach ($deltas as $delta) {
            $out .= ArabicTextFixer::streamDelta($delta, $carry);
        }
        $this->assertSame(self::raw('مرحبًا، كيف حالك؟ 1{c}000'), $out);
        $this->assertSame(ArabicTextFixer::fix(implode('', $deltas)), $out);
    }

    #[Test]
    public function stream_wrapper_keeps_one_carry_per_stream(): void
    {
        $a = [];
        $b = [];
        $streamA = ArabicTextFixer::stream(function (string $t) use (&$a) {
            $a[] = $t;
        });
        $streamB = ArabicTextFixer::stream(function (string $t) use (&$b) {
            $b[] = $t;
        });
        $streamA('الحفل');
        $streamB('Hello');
        $streamA(self::raw('{c} المعرض'));
        $streamB(self::raw('{c} world'));
        $this->assertSame(['الحفل', '، المعرض'], $a);
        $this->assertSame(['Hello', self::raw('{c} world')], $b);
    }

    #[Test]
    public function stream_deltas_leave_spacing_and_dashes_to_the_stored_text(): void
    {
        $raw = self::raw('الحفل{_}{c} مجاني {em} يبدأ');
        $carry = '';
        $streamed = '';
        foreach (mb_str_split($raw, 3) as $delta) {
            $streamed .= ArabicTextFixer::streamDelta($delta, $carry);
        }
        $this->assertSame(self::raw('الحفل{_}، مجاني {em} يبدأ'), $streamed);
        $this->assertSame('الحفل، مجاني، يبدأ', ArabicTextFixer::fix($raw));
        $this->assertSame(ArabicTextFixer::fix($raw), ArabicTextFixer::fix($streamed));
    }

    #[Test]
    public function a_line_break_resets_the_stream_context(): void
    {
        $carry = '';
        $out = ArabicTextFixer::streamDelta("الحفل\n", $carry).ArabicTextFixer::streamDelta(self::raw('{c}x'), $carry);
        $this->assertSame("الحفل\n".self::raw('{c}x'), $out);
        $this->assertSame($out, ArabicTextFixer::fix($out));
    }
}

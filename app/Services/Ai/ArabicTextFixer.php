<?php

namespace App\Services\Ai;

/**
 * Deterministic punctuation clean-up for Arabic model output
 * (docs/arabic_style_rules.md A-22, B-32, B-36, B-47). Only touches marks
 * that sit next to Arabic letters, so English text, code, URLs, numbers
 * (1,000) and JSON pass through unchanged.
 *
 * Full text (stored / returned answers), fix():
 *  - Latin «,» «;» «?» after an Arabic word → «،» «؛» «؟» (not when a
 *    lowercase Latin word follows, i.e. English prose naming an Arabic
 *    place, nor «?=» / «?&» in a URL; codes such as OMR or QR may follow);
 *  - em dash, or a spaced en dash, between Arabic words → «، »;
 *  - no whitespace between an Arabic word and ، ؛ ؟ ! (and . : at a word end);
 *  - one space after «،» «؛» before an Arabic letter, no doubled spaces there;
 *  - Arabic-Indic digits → Western digits (product standard).
 *
 * Streams, stream()/streamDelta(): only the single-character mappings,
 * with the previous delta's last non-space character carried over, so a
 * comma that starts a delta after an Arabic word is still mapped. Spacing
 * and dash rules need both sides of a chunk boundary and are left to the
 * stored text; see streamDelta().
 */
final class ArabicTextFixer
{
    /** Arabic letters, diacritics, tatweel (no digits, no Arabic punctuation). */
    private const LETTER = '\x{0621}-\x{065F}\x{066E}-\x{06D3}\x{06D5}\x{06FA}-\x{06FF}\x{0640}\x{0670}';

    /** A character that ends an Arabic word: a letter or a closing «. */
    private const WORD_END = '['.self::LETTER.'\x{00BB}]';

    private const MAP = [',' => '،', ';' => '؛', '?' => '؟'];

    private const DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    public static function fix(string $text): string
    {
        if ($text === '' || ! preg_match('/['.self::LETTER.'\x{0660}-\x{0669}]/u', $text)) {
            return $text;
        }
        $a = self::LETTER;
        $text = strtr($text, self::DIGITS);

        // , ; ? after an Arabic word (optional spaces), unless English prose follows.
        $text = preg_replace_callback(
            '/('.self::WORD_END.')(\h*)([,;?])(?!\h*[a-z])(?![=&])/u',
            fn ($m) => $m[1].$m[2].self::MAP[$m[3]],
            $text,
        );
        // Em dash (spaced or not) / spaced en dash between Arabic words or before a number.
        $text = preg_replace('/(['.$a.'\x{00BB}])\h*—\h*(?=['.$a.'\x{00AB}0-9])/u', '$1، ', $text);
        $text = preg_replace('/(['.$a.'\x{00BB}])\h+–\h+(?=['.$a.'\x{00AB}])/u', '$1، ', $text);
        // No space before Arabic punctuation and ! after an Arabic word; . : only at a word end.
        $text = preg_replace('/('.self::WORD_END.')\h+(?=[\x{060C}\x{061B}\x{061F}!])/u', '$1', $text);
        $text = preg_replace('/('.self::WORD_END.')\h+([.:])(?=\s|$)/u', '$1$2', $text);
        // «،كلمة» → «، كلمة»; «،   كلمة» → «، كلمة».
        $text = preg_replace('/([\x{060C}\x{061B}])(?:\h{2,}|(?=['.$a.'\x{00AB}]))/u', '$1 ', $text);

        return $text;
    }

    /**
     * Per-delta streaming fix. $carry holds the last character of the
     * previous deltas of the same stream that is not a horizontal space
     * (start with ''); it is updated.
     *
     * Differences from fix() on the joined text, by design: spaces before
     * punctuation and em/en dashes are left as they are, a missing space
     * after «،» is not added, and a mark at the very end of a delta is
     * mapped without looking at the next delta (fix() keeps it Latin when a
     * lowercase Latin word follows, which is rare in Arabic answers).
     */
    public static function streamDelta(string $delta, string &$carry): string
    {
        if ($delta === '') {
            return $delta;
        }
        $delta = strtr($delta, self::DIGITS);
        $chars = mb_str_split($delta);
        $n = count($chars);
        $prev = $carry;
        for ($i = 0; $i < $n; $i++) {
            $c = $chars[$i];
            if (isset(self::MAP[$c]) && $prev !== '' && preg_match('/^'.self::WORD_END.'$/u', $prev)) {
                $next = '';
                for ($j = $i + 1; $j < $n; $j++) {
                    if (! preg_match('/^\h$/u', $chars[$j])) {
                        $next = $chars[$j];
                        break;
                    }
                }
                $immediate = $chars[$i + 1] ?? '';
                if (! preg_match('/^[a-z]$/', $next) && ! in_array($immediate, ['=', '&'], true)) {
                    $chars[$i] = $c = self::MAP[$c];
                }
            }
            if (! preg_match('/^\h$/u', $c)) {
                $prev = $c;              // a line break resets the context, as in fix()
            }
        }
        $carry = $prev;

        return implode('', $chars);
    }

    /** Wraps a delta callback so every delta of one stream goes through streamDelta(). */
    public static function stream(callable $emit): callable
    {
        $carry = '';

        return function (string $delta) use (&$carry, $emit) {
            $emit(self::streamDelta($delta, $carry));
        };
    }
}

<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Models\User;

/**
 * Organizer copilot (contract §A5) — small structured-output calls on the
 * smart model. Results are suggestions: the admin page inserts them into
 * the form fields and nothing is saved until the admin presses Save.
 * Arabic fields go through ArabicTextFixer before they are returned.
 */
class CopilotService
{
    public function __construct(private OpenAiClient $client) {}

    private function run(Tenant $tenant, User $user, string $feature, string $task, array $input, array $schema): array
    {
        $call = new AiCall($tenant, 'copilot.'.$feature, $user);
        $payload = $this->client->payload('smart', [
            'instructions' => Prompts::copilot($tenant)."\nTask: ".$task,
            'input' => [['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'copilot_'.str_replace('.', '_', $feature), 'schema' => $schema, 'strict' => true]],
            'max_output_tokens' => (int) config('ai.copilot.max_output_tokens', 1500),
            'safety_identifier' => $call->safetyIdentifier(),
        ]);
        $data = $this->client->create($payload, $call)->json();
        if (! $data) {
            throw AiException::unavailable('copilot_invalid_json');
        }

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
    }

    /** Punctuation clean-up of the Arabic fields of a result. */
    private static function arabic(array $out, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($out[$key]) && is_string($out[$key])) {
                $out[$key] = ArabicTextFixer::fix($out[$key]);
            }
        }

        return $out;
    }

    private static function obj(array $stringFields): array
    {
        return [
            'type' => 'object',
            'properties' => array_fill_keys($stringFields, ['type' => 'string']),
            'required' => $stringFields,
            'additionalProperties' => false,
        ];
    }

    /** Event description in both languages from the form facts. */
    public function describeEvent(Tenant $tenant, User $user, array $facts): array
    {
        return self::arabic($this->run($tenant, $user, 'describe', 'Write an engaging event description (60–110 words each) in Arabic (description_ar) and English (description_en) from these facts. Mention the venue and dates naturally if given; mention the price only if given ("free" when 0). One or two short paragraphs, no lists.', $facts, self::obj(['description_ar', 'description_en'])), ['description_ar']);
    }

    /**
     * Summary + description of a place to visit / directory entry / page,
     * from the form facts only (no invented history, hours, fees, distances).
     */
    public function describeContent(Tenant $tenant, User $user, array $facts): array
    {
        $task = 'Write visitor-facing text for this '.($facts['kind'] ?? 'place').' in Arabic and English: summary_ar / summary_en (one sentence, at most 180 characters) and description_ar / description_en (1–2 short paragraphs, 40–90 words each, paragraphs separated by a blank line, no lists). Use ONLY the facts given (name, category, wilayat, address, notes…). Do not add history, founding dates, architecture details, opening hours, fees, distances, ratings or claims that are not in the facts; when facts are thin, write fewer, neutral sentences.';

        return self::arabic($this->run($tenant, $user, 'describe_content', $task, $facts, self::obj(['summary_ar', 'summary_en', 'description_ar', 'description_en'])), ['summary_ar', 'description_ar']);
    }

    /** Translate a set of fields between Arabic and English. */
    public function translate(Tenant $tenant, User $user, array $fields, string $from, string $to): array
    {
        $names = array_keys($fields);
        $task = 'Translate every field from '.($from === 'ar' ? 'Arabic' : 'English').' to '.($to === 'ar' ? 'Arabic (Modern Standard, Gulf-friendly)' : 'English').'. Keep meaning, names of places/people (transliterate), numbers, dates and prices exactly; natural, not literal. Some values are simple HTML (p, br, strong, em, ul, ol, li, a, h3, h4): keep every tag and attribute exactly and translate only the text between them. Return the same keys.';
        $out = $this->run($tenant, $user, 'translate', $task, $fields, self::obj($names));

        $out = array_intersect_key($out, array_flip($names));

        return $to === 'ar' ? self::arabic($out, $names) : $out;
    }

    /** Shorten a text in its language. */
    public function shorten(Tenant $tenant, User $user, string $text, string $locale, int $maxChars): array
    {
        $out = $this->run($tenant, $user, 'shorten', 'Shorten the text to at most '.$maxChars.' characters in the same language ('.($locale === 'ar' ? 'Arabic' : 'English').'), keeping the key facts (what, where, when, price). Return {"text": "..."}.', ['text' => $text], self::obj(['text']));

        return $locale === 'ar' ? self::arabic($out, ['text']) : $out;
    }

    /** Draft a bilingual announcement from a one-line brief. */
    public function draftAnnouncement(Tenant $tenant, User $user, string $brief, ?string $type): array
    {
        return self::arabic($this->run($tenant, $user, 'announcement', 'Draft an in-app announcement for visitors from the organizer\'s brief: title_ar / title_en (max 60 chars), content_ar / content_en (max 280 chars, 1–3 sentences, polite and clear; for warnings/emergencies be calm and direct). Only facts from the brief.', ['brief' => $brief, 'type' => $type], self::obj(['title_ar', 'title_en', 'content_ar', 'content_en'])), ['title_ar', 'content_ar']);
    }
}

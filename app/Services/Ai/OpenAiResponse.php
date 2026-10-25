<?php

namespace App\Services\Ai;

/**
 * A (final) Responses API object: output items, text, function calls, usage.
 */
final class OpenAiResponse
{
    public function __construct(public readonly array $raw) {}

    public function output(): array
    {
        return array_values(array_filter((array) ($this->raw['output'] ?? []), 'is_array'));
    }

    public function status(): string
    {
        return (string) ($this->raw['status'] ?? 'completed');
    }

    public function model(): ?string
    {
        return $this->raw['model'] ?? null;
    }

    public function text(): string
    {
        if (isset($this->raw['output_text']) && is_string($this->raw['output_text'])) {
            return $this->raw['output_text'];
        }
        $text = '';
        foreach ($this->output() as $item) {
            if (($item['type'] ?? null) === 'message') {
                foreach ((array) ($item['content'] ?? []) as $part) {
                    if (($part['type'] ?? null) === 'output_text') {
                        $text .= (string) ($part['text'] ?? '');
                    }
                }
            }
        }

        return $text;
    }

    /** @return list<array{call_id: string, name: string, arguments: array}> */
    public function functionCalls(): array
    {
        $calls = [];
        foreach ($this->output() as $item) {
            if (($item['type'] ?? null) === 'function_call') {
                $args = json_decode((string) ($item['arguments'] ?? '{}'), true);
                $calls[] = [
                    'call_id' => (string) ($item['call_id'] ?? $item['id'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'arguments' => is_array($args) ? $args : [],
                ];
            }
        }

        return $calls;
    }

    /** Structured output (text.format json_schema) decoded, or null. */
    public function json(): ?array
    {
        $text = trim($this->text());
        if ($text === '') {
            return null;
        }
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $data : null;
    }

    public function inputTokens(): int
    {
        return (int) data_get($this->raw, 'usage.input_tokens', 0);
    }

    public function outputTokens(): int
    {
        return (int) data_get($this->raw, 'usage.output_tokens', 0);
    }

    public function cachedTokens(): int
    {
        return (int) data_get($this->raw, 'usage.input_tokens_details.cached_tokens', 0);
    }

    public function reasoningTokens(): int
    {
        return (int) data_get($this->raw, 'usage.output_tokens_details.reasoning_tokens', 0);
    }

    public function totalTokens(): int
    {
        return $this->inputTokens() + $this->outputTokens();
    }
}

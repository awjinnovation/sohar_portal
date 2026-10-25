<?php

namespace App\Services\Ai;

/**
 * Incremental Server-Sent Events parser (OpenAI streaming responses).
 * Feed raw chunks; complete events are returned as
 * ['event' => ?string, 'data' => string]. Handles \n / \r\n, multi-line
 * data, comments and events split across chunks.
 */
class SseParser
{
    private string $buffer = '';

    /** @return list<array{event: ?string, data: string}> */
    public function feed(string $chunk): array
    {
        $this->buffer .= str_replace("\r\n", "\n", $chunk);
        $events = [];

        while (($pos = strpos($this->buffer, "\n\n")) !== false) {
            $block = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 2);
            if (($event = $this->parseBlock($block)) !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /** Flush a trailing event that was not terminated by a blank line. */
    public function finish(): array
    {
        $block = trim($this->buffer, "\n");
        $this->buffer = '';

        return $block !== '' && ($event = $this->parseBlock($block)) !== null ? [$event] : [];
    }

    private function parseBlock(string $block): ?array
    {
        $event = null;
        $data = [];
        foreach (explode("\n", $block) as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }
            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = str_starts_with($value, ' ') ? substr($value, 1) : $value;
            if ($field === 'event') {
                $event = $value;
            } elseif ($field === 'data') {
                $data[] = $value;
            }
        }

        return $data === [] && $event === null ? null : ['event' => $event, 'data' => implode("\n", $data)];
    }
}

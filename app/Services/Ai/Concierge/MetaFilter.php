<?php

namespace App\Services\Ai\Concierge;

use App\Services\Ai\Prompts;

/**
 * Splits the streamed answer into visible text and the trailing
 * `<<<META {...}>>>` line (cards + suggestions). Text is released as soon
 * as it cannot be the start of the marker, so the visitor never sees it.
 */
class MetaFilter
{
    private string $pending = '';

    private string $visible = '';

    private ?string $meta = null;

    /** Feed a text delta; returns the part that is safe to show now. */
    public function feed(string $delta): string
    {
        if ($this->meta !== null) {
            $this->meta .= $delta;

            return '';
        }
        $this->pending .= $delta;

        $pos = strpos($this->pending, '<<<');
        if ($pos !== false) {
            $out = substr($this->pending, 0, $pos);
            $this->meta = substr($this->pending, $pos);
            $this->pending = '';
            $this->visible .= $out;

            return $out;
        }

        // Hold back a trailing "<" / "<<" that may start the marker.
        $hold = 0;
        if (str_ends_with($this->pending, '<<')) {
            $hold = 2;
        } elseif (str_ends_with($this->pending, '<')) {
            $hold = 1;
        }
        $out = substr($this->pending, 0, strlen($this->pending) - $hold);
        $this->pending = substr($this->pending, strlen($this->pending) - $hold);
        $this->visible .= $out;

        return $out;
    }

    /** End of stream: remaining visible text (to emit) — call once. */
    public function flush(): string
    {
        $out = $this->pending;
        $this->pending = '';
        $this->visible .= $out;

        return $out;
    }

    public function visibleText(): string
    {
        return trim($this->visible);
    }

    /** Decoded META object or null. */
    public function meta(): ?array
    {
        if ($this->meta === null) {
            return null;
        }
        $raw = substr($this->meta, strlen('<<<'));
        $raw = preg_replace('/^\s*META\s*/i', '', $raw);
        $end = strrpos($raw, '>>>');
        if ($end !== false) {
            $raw = substr($raw, 0, $end);
        }
        $start = strpos($raw, '{');
        $stop = strrpos($raw, '}');
        if ($start === false || $stop === false) {
            return null;
        }
        $data = json_decode(substr($raw, $start, $stop - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    /** Non-streaming helper: split a complete answer. */
    public static function split(string $text): array
    {
        $f = new self;
        $f->feed($text);
        $f->flush();

        return [$f->visibleText(), $f->meta()];
    }

    public static function marker(): string
    {
        return Prompts::META_OPEN;
    }
}

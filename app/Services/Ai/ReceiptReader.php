<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use App\Models\User;

/**
 * Reads a card terminal receipt photo with the vision model
 * (docs/gate_sales_contract.md §9). Transcription only: the caller decides
 * whether the receipt pays the order. Never throws: when the model cannot be
 * used the result says why (`unavailable`, `budget`) and `fields` is null.
 */
class ReceiptReader
{
    public const STATUS_OK = 'ok';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_BUDGET = 'budget';

    public const FEATURE = 'gate_receipt';

    public function __construct(private OpenAiClient $client, private AiUsage $usage) {}

    /**
     * @return array{status: string, model: ?string, fields: ?array<string, mixed>}
     */
    public function read(Tenant $tenant, string $bytes, string $mime, ?User $staff = null): array
    {
        $none = fn (string $status) => ['status' => $status, 'model' => null, 'fields' => null];
        $call = new AiCall($tenant, self::FEATURE, $staff);

        if (! $this->client->configured()) {
            $this->usage->record($call, null, null, 0, 'error', 'not_configured');

            return $none(self::STATUS_UNAVAILABLE);
        }
        try {
            $this->usage->ensureBudget($tenant);
        } catch (AiException $e) {
            $this->usage->recordBlocked($call, $e);

            return $none(self::STATUS_BUDGET);
        }

        [$bytes, $mime] = $this->shrink($bytes, $mime);
        $cfg = (array) config('ai.receipt', []);
        $payload = $this->client->payload((string) ($cfg['tier'] ?? 'smart'), [
            'instructions' => Prompts::receiptReader($tenant),
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => 'Read this receipt.'],
                    ['type' => 'input_image', 'image_url' => 'data:'.$mime.';base64,'.base64_encode($bytes), 'detail' => (string) ($cfg['image_detail'] ?? 'high')],
                ],
            ]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'card_terminal_receipt', 'schema' => self::schema(), 'strict' => true]],
            'max_output_tokens' => (int) ($cfg['max_output_tokens'] ?? 900),
            'safety_identifier' => $call->safetyIdentifier(),
        ]);

        try {
            $out = $this->client->create($payload, $call, ['timeout' => (int) ($cfg['timeout'] ?? 30), 'retries' => (int) ($cfg['retries'] ?? 1)]);
        } catch (AiException $e) {
            return $none(self::STATUS_UNAVAILABLE);
        }

        $fields = self::normalize($out->json());

        return $fields === null
            ? ['status' => self::STATUS_UNAVAILABLE, 'model' => $out->model(), 'fields' => null]
            : ['status' => self::STATUS_OK, 'model' => $out->model(), 'fields' => $fields];
    }

    /** JSON schema of the model's answer (strict: every key present, null when unknown). */
    public static function schema(): array
    {
        $text = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'is_receipt' => ['type' => 'boolean'],
                'transaction_status' => ['type' => 'string', 'enum' => ['approved', 'declined', 'voided', 'refund', 'unknown']],
                'amount' => $text,
                'currency' => $text,
                'date' => $text,
                'time' => $text,
                'terminal_id' => $text,
                'merchant_id' => $text,
                'merchant_name' => $text,
                'approval_code' => $text,
                'rrn' => $text,
                'card_last4' => $text,
                'card_scheme' => $text,
                'legibility' => ['type' => 'string', 'enum' => ['clear', 'partial', 'poor']],
                'screen_photo' => ['type' => 'boolean'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['is_receipt', 'transaction_status', 'amount', 'currency', 'date', 'time', 'terminal_id', 'merchant_id',
                'merchant_name', 'approval_code', 'rrn', 'card_last4', 'card_scheme', 'legibility', 'screen_photo', 'notes'],
            'additionalProperties' => false,
        ];
    }

    /** The model's JSON → trimmed, typed fields (null when it is not the expected shape). */
    public static function normalize(?array $j): ?array
    {
        if (! is_array($j) || ! array_key_exists('is_receipt', $j)) {
            return null;
        }
        $str = function ($v, int $max = 60): ?string {
            if (! is_scalar($v)) {
                return null;
            }
            $v = trim(preg_replace('/\s+/u', ' ', (string) $v));

            return $v === '' || in_array(strtolower($v), ['null', 'n/a', 'none', '-'], true) ? null : mb_substr($v, 0, $max);
        };
        $status = (string) ($j['transaction_status'] ?? 'unknown');
        $legibility = (string) ($j['legibility'] ?? 'poor');

        return [
            'is_receipt' => (bool) $j['is_receipt'],
            'transaction_status' => in_array($status, ['approved', 'declined', 'voided', 'refund', 'unknown'], true) ? $status : 'unknown',
            'amount' => $str($j['amount'] ?? null, 20),
            'currency' => $str($j['currency'] ?? null, 10),
            'date' => $str($j['date'] ?? null, 10),
            'time' => $str($j['time'] ?? null, 8),
            'terminal_id' => $str($j['terminal_id'] ?? null, 40),
            'merchant_id' => $str($j['merchant_id'] ?? null, 40),
            'merchant_name' => $str($j['merchant_name'] ?? null, 80),
            'approval_code' => $str($j['approval_code'] ?? null, 20),
            'rrn' => $str($j['rrn'] ?? null, 30),
            'card_last4' => $str($j['card_last4'] ?? null, 4),
            'card_scheme' => $str($j['card_scheme'] ?? null, 20),
            'legibility' => in_array($legibility, ['clear', 'partial', 'poor'], true) ? $legibility : 'poor',
            'screen_photo' => (bool) ($j['screen_photo'] ?? false),
            'notes' => (string) $str($j['notes'] ?? '', 200),
        ];
    }

    /**
     * Large photos are scaled down (long side `ai.receipt.max_image_side`)
     * and re-encoded as JPEG before they are sent; the stored original is
     * left as it is. Without GD, or for an image GD cannot open, the bytes go
     * as they are.
     *
     * @return array{0: string, 1: string}
     */
    private function shrink(string $bytes, string $mime): array
    {
        $max = (int) config('ai.receipt.max_image_side', 2000);
        if (! function_exists('imagecreatefromstring') || ! ($info = @getimagesizefromstring($bytes))) {
            return [$bytes, $mime];
        }
        [$w, $h] = $info;
        if (max($w, $h) <= $max && strlen($bytes) <= 1_500_000) {
            return [$bytes, $mime];
        }
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return [$bytes, $mime];
        }
        $scale = min(1, $max / max($w, $h));
        $scaled = $scale < 1 ? imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))) : $img;
        ob_start();
        imagejpeg($scaled ?: $img, null, 85);
        $out = (string) ob_get_clean();

        return $out !== '' ? [$out, 'image/jpeg'] : [$bytes, $mime];
    }
}

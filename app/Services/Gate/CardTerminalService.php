<?php

namespace App\Services\Gate;

use App\Exceptions\ApiException;
use App\Models\BoxOfficeOrder;
use App\Models\BoxOfficeReceipt;
use App\Models\GateDevice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\ReceiptReader;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Wallet\WalletAdminService;
use App\Support\Money;
use App\Support\TenantTime;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gate box office paid on the organizer's card terminal
 * (docs/gate_sales_contract.md §9).
 *
 * Staff charge the visitor's card on the gate's own POS terminal and send a
 * photo of the receipt. The photo is stored, read by the vision model
 * (ReceiptReader: transcription only, it never sees the order) and judged
 * here against the order and against every receipt already accepted:
 *
 *   verified  every check passed → the order is paid, tickets issued;
 *   review    nothing contradicts the order but something could not be
 *             checked (unreadable field, AI unavailable) → paid and flagged
 *             for a supervisor (`gate_sales.pos_accept_unverified`, default),
 *             or held until a supervisor approves it in the admin;
 *   rejected  wrong amount, date, currency or terminal, not approved, not a
 *             receipt, or already used for another order → not paid, the
 *             staff may retake the photo (MAX_ATTEMPTS per order) or cancel.
 *
 * Duplicates: the same image (SHA-256) sent for another order, or the same
 * receipt reference (RRN) or approval code + terminal + amount + date as a
 * receipt accepted for another order. Accepted receipts hold unique keys, so
 * two gates cannot accept the same receipt at the same moment.
 *
 * Payment: a Payment with method `pos` completed through the normal
 * fulfillment (tickets once, points, the order marked paid; an order that
 * expired meanwhile is completed when its seats are still free, else it
 * becomes paid_unfulfilled and the money is returned on the terminal).
 */
class CardTerminalService
{
    public const MAX_ATTEMPTS = 5;

    public const DISK = 'local';

    public const MAX_IMAGE_KB = 10240;

    public const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** Currency spellings printed on Omani terminals, after removing spaces and dots. */
    private const CURRENCY_ALIASES = ['OMR' => ['OMR', 'RO', 'رع', 'ريال', 'ريالعماني']];

    public function __construct(
        private BoxOfficeService $boxOffice,
        private CheckoutService $checkout,
        private PaymentFulfillmentService $fulfillment,
        private ReceiptReader $reader,
    ) {}

    // ------------------------------------------------------------------
    // Gate: POST /gate/sales/orders/{id}/receipt
    // ------------------------------------------------------------------

    /**
     * @return array{0: BoxOfficeOrder, 1: BoxOfficeReceipt, 2: bool} order, receipt, created (false = replay)
     */
    public function submit(Tenant $tenant, BoxOfficeOrder $order, GateDevice $device, User $staff, UploadedFile $image, string $key): array
    {
        if (! $order->isCardTerminal()) {
            throw new ApiException('WRONG_PAYMENT_METHOD', __('box_office.errors.wrong_payment_method'), 409);
        }

        try {
            // One photo at a time per order: a retry while the first is being read waits for it.
            return Cache::lock('box_office_receipt:'.$order->id, 120)
                ->block(60, fn () => $this->submitLocked($tenant, $order, $device, $staff, $image, $key));
        } catch (LockTimeoutException) {
            throw new ApiException('RECEIPT_BUSY', __('box_office.errors.receipt_busy'), 409);
        }
    }

    private function submitLocked(Tenant $tenant, BoxOfficeOrder $order, GateDevice $device, User $staff, UploadedFile $image, string $key): array
    {
        if ($replay = BoxOfficeReceipt::withoutTenantScope()->where('order_id', $order->id)->where('idempotency_key', $key)->first()) {
            return [$order->fresh(), $replay, false];
        }

        $order = $this->boxOffice->expireIfDue($order->fresh());
        // An expired order may still be paid: the card may have been charged just before.
        if (! in_array($order->status, [BoxOfficeOrder::STATUS_PENDING, BoxOfficeOrder::STATUS_EXPIRED], true)) {
            throw new ApiException('ORDER_NOT_PENDING', __('box_office.errors.order_not_pending'), 409, ['status' => $order->status]);
        }

        $bytes = (string) $image->get();
        $sha = hash('sha256', $bytes);
        // The same photo again for this order: same answer, no new attempt.
        if ($same = BoxOfficeReceipt::withoutTenantScope()->where('order_id', $order->id)->where('sha256', $sha)->orderByDesc('id')->first()) {
            return [$order->fresh(), $same, false];
        }
        if ($order->receipts()->count() >= self::MAX_ATTEMPTS) {
            throw new ApiException('RECEIPT_ATTEMPTS', __('box_office.errors.receipt_attempts'), 429, ['max' => self::MAX_ATTEMPTS]);
        }

        $mime = (string) $image->getMimeType();
        $path = sprintf('box-office-receipts/%d/%s/%s.%s', $tenant->id, now()->format('Y/m'), Str::uuid(), self::MIMES[$mime] ?? 'jpg');
        Storage::disk(self::DISK)->put($path, $bytes);

        $read = $this->reader->read($tenant, $bytes, $mime, $staff);
        $judged = $this->judge($tenant, $order, $sha, $read);

        $receipt = new BoxOfficeReceipt([
            'order_id' => $order->id,
            'device_id' => $device->id,
            'staff_user_id' => $staff->id,
            'idempotency_key' => $key,
            'disk' => self::DISK,
            'path' => $path,
            'sha256' => $sha,
            'mime' => $mime,
            'size' => strlen($bytes),
            'ai_status' => $read['status'],
            'ai_model' => $read['model'],
            'extracted' => $read['fields'],
        ] + $judged['columns'] + [
            'verdict' => $judged['verdict'],
            'reasons' => $judged['reasons'],
            'duplicate_of_order_id' => $judged['duplicate_of'],
        ]);
        $receipt->tenant_id = $tenant->id;
        try {
            $receipt->save();
        } catch (UniqueConstraintViolationException) {
            Storage::disk(self::DISK)->delete($path);
            $replay = BoxOfficeReceipt::withoutTenantScope()->where('order_id', $order->id)->where('idempotency_key', $key)->firstOrFail();

            return [$order->fresh(), $replay, false];
        }

        $accept = $receipt->verdict === BoxOfficeReceipt::VERIFIED
            || ($receipt->verdict === BoxOfficeReceipt::REVIEW && BoxOfficeService::settings($tenant)['pos_accept_unverified']);
        if ($accept) {
            $this->accept($order, $receipt, null);
        }

        return [$order->fresh(), $receipt->fresh(), true];
    }

    // ------------------------------------------------------------------
    // Admin decisions (Admin → Gate sales → order)
    // ------------------------------------------------------------------

    /** A supervisor accepts a receipt the checks did not (rejected, or held for review): the order is paid. */
    public function approve(BoxOfficeOrder $order, BoxOfficeReceipt $receipt, User $admin, ?string $note = null): BoxOfficeOrder
    {
        $order = $this->boxOffice->expireIfDue($order);
        if (! $order->isCardTerminal() || (int) $receipt->order_id !== (int) $order->id || $receipt->accepted
            || ! in_array($order->status, [BoxOfficeOrder::STATUS_PENDING, BoxOfficeOrder::STATUS_EXPIRED], true)) {
            throw new ApiException('INVALID_STATE', __('box_office.errors.receipt_not_approvable'), 409);
        }
        $receipt->forceFill(['decision' => 'approved', 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $note])->save();

        return $this->accept($order, $receipt, $admin);
    }

    /** A supervisor looked at a sale paid with a receipt that needed review and keeps it. */
    public function clearReview(BoxOfficeOrder $order, User $admin, ?string $note = null): BoxOfficeOrder
    {
        if (! $order->needs_review) {
            throw new ApiException('INVALID_STATE', __('box_office.errors.receipt_not_in_review'), 409);
        }
        $order->receipts()->where('accepted', true)->reorder('id', 'desc')->first()
            ?->forceFill(['decision' => 'cleared', 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $note])->save();
        $order->forceFill(['needs_review' => false])->save();

        return $order->fresh();
    }

    /**
     * A supervisor cancels a card terminal sale (the receipt was not genuine,
     * or the money is returned on the terminal): its tickets are cancelled,
     * the payment and the order become refunded. The receipt stays used.
     */
    public function void(BoxOfficeOrder $order, User $admin, string $reason, WalletAdminService $admins): BoxOfficeOrder
    {
        $payment = $order->payment_id ? Payment::withoutTenantScope()->find($order->payment_id) : null;
        if (! $order->isCardTerminal() || ! $payment || $payment->method !== Payment::METHOD_POS
            || ! in_array($order->status, [BoxOfficeOrder::STATUS_PAID, BoxOfficeOrder::STATUS_PAID_UNFULFILLED], true)) {
            throw new ApiException('INVALID_STATE', __('box_office.errors.sale_not_voidable'), 409);
        }
        $admins->refundPayment($payment, $admin, $reason);
        $order->receipts()->where('accepted', true)->reorder('id', 'desc')->first()
            ?->forceFill(['decision' => 'voided', 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_note' => $reason])->save();
        $order = $order->fresh();
        $order->forceFill(['needs_review' => false])->save();

        return $order->fresh();
    }

    // ------------------------------------------------------------------
    // Checks
    // ------------------------------------------------------------------

    /**
     * @param  array{status: string, model: ?string, fields: ?array}  $read
     * @return array{verdict: string, reasons: list<array>, duplicate_of: ?int, columns: array}
     */
    public function judge(Tenant $tenant, BoxOfficeOrder $order, string $sha, array $read): array
    {
        $reasons = [];
        $add = function (string $level, string $code, array $data = []) use (&$reasons) {
            $reasons[] = ['code' => $code, 'level' => $level] + ($data ? ['data' => $data] : []);
        };
        $duplicateOf = null;
        $columns = ['amount_minor' => null, 'receipt_date' => null, 'terminal_id' => null, 'approval_code' => null, 'rrn' => null, 'card_last4' => null];

        $sameImage = BoxOfficeReceipt::withoutTenantScope()->where('tenant_id', $tenant->id)->where('sha256', $sha)
            ->where('order_id', '!=', $order->id)->orderByDesc('id')->first();
        if ($sameImage) {
            $duplicateOf = (int) $sameImage->order_id;
            $add('reject', 'duplicate_image', ['reference' => $this->reference($duplicateOf)]);
        }

        $f = $read['fields'];
        if ($read['status'] !== ReceiptReader::STATUS_OK || ! is_array($f)) {
            $add('review', $read['status'] === ReceiptReader::STATUS_BUDGET ? 'ai_budget' : 'ai_unavailable');
        } elseif (! $f['is_receipt']) {
            $add('reject', 'not_a_receipt');
        } else {
            $decimals = $tenant->currencyDecimals();
            $columns = [
                'amount_minor' => self::amountMinor($f['amount'], $decimals),
                'receipt_date' => self::date($f['date']),
                'terminal_id' => self::code($f['terminal_id'], 2, 40),
                'approval_code' => self::code($f['approval_code'], 2, 20),
                'rrn' => self::code($f['rrn'], 6, 30),
                'card_last4' => self::last4($f['card_last4']),
            ];

            match ($f['transaction_status']) {
                'approved' => null,
                'declined', 'voided', 'refund' => $add('reject', 'not_approved', ['status' => $f['transaction_status']]),
                default => $columns['approval_code'] ? null : $add('review', 'status_unreadable'),
            };

            if ($columns['amount_minor'] === null) {
                $add('review', 'amount_unreadable');
            } elseif ($columns['amount_minor'] !== (int) $order->total_minor) {
                $add('reject', 'amount_mismatch', ['expected' => $tenant->formatMoney((int) $order->total_minor), 'found' => $tenant->formatMoney($columns['amount_minor'])]);
            }

            if ($f['currency'] !== null && ! self::currencyMatches($f['currency'], (string) $order->currency)) {
                $add('reject', 'currency_mismatch', ['found' => $f['currency']]);
            }

            if ($columns['receipt_date'] === null) {
                $add('review', 'date_unreadable');
            } elseif (! in_array($columns['receipt_date'], $this->allowedDates($tenant, $order), true)) {
                $add('reject', 'wrong_date', ['found' => $columns['receipt_date']]);
            }

            $terminals = BoxOfficeService::settings($tenant)['pos_terminal_ids'];
            if ($terminals) {
                if ($columns['terminal_id'] === null) {
                    $add('review', 'terminal_unreadable');
                } elseif (! in_array($columns['terminal_id'], $terminals, true)) {
                    $add('reject', 'unknown_terminal', ['found' => $columns['terminal_id']]);
                }
            }

            if ($columns['rrn'] === null && $columns['approval_code'] === null) {
                $add('review', 'reference_unreadable');
            }
            if ($f['legibility'] === 'poor') {
                $add('review', 'low_legibility');
            }
            if ($f['screen_photo']) {
                $add('review', 'screen_photo');
            }

            [$rrnKey, $authKey] = self::keys($columns);
            if (! $sameImage && ($rrnKey || $authKey)) {
                $used = BoxOfficeReceipt::withoutTenantScope()->where('tenant_id', $tenant->id)->where('accepted', true)
                    ->where('order_id', '!=', $order->id)
                    ->where(fn ($q) => $q->when($rrnKey, fn ($w) => $w->orWhere('rrn_key', $rrnKey))->when($authKey, fn ($w) => $w->orWhere('auth_key', $authKey)))
                    ->first();
                if ($used) {
                    $duplicateOf = (int) $used->order_id;
                    $add('reject', 'duplicate_receipt', ['reference' => $this->reference($duplicateOf)]);
                }
            }
        }

        $levels = array_column($reasons, 'level');
        $verdict = in_array('reject', $levels, true) ? BoxOfficeReceipt::REJECTED
            : (in_array('review', $levels, true) ? BoxOfficeReceipt::REVIEW : BoxOfficeReceipt::VERIFIED);

        return ['verdict' => $verdict, 'reasons' => $reasons, 'duplicate_of' => $duplicateOf, 'columns' => $columns];
    }

    /**
     * Reserve the receipt (unique keys) and pay the order with it. A clash
     * means another order accepted the same receipt at the same moment: the
     * receipt becomes a rejected duplicate, unless a supervisor approves it
     * ($by), who may override (the keys are then left empty).
     */
    private function accept(BoxOfficeOrder $order, BoxOfficeReceipt $receipt, ?User $by): BoxOfficeOrder
    {
        [$rrnKey, $authKey] = self::keys([
            'amount_minor' => $receipt->amount_minor, 'receipt_date' => $receipt->receipt_date?->toDateString(),
            'terminal_id' => $receipt->terminal_id, 'approval_code' => $receipt->approval_code, 'rrn' => $receipt->rrn,
        ]);
        try {
            DB::transaction(fn () => $receipt->forceFill(['accepted' => true, 'rrn_key' => $rrnKey, 'auth_key' => $authKey])->save());
        } catch (UniqueConstraintViolationException) {
            $other = BoxOfficeReceipt::withoutTenantScope()->where('tenant_id', $receipt->tenant_id)->where('accepted', true)
                ->where(fn ($q) => $q->when($rrnKey, fn ($w) => $w->orWhere('rrn_key', $rrnKey))->when($authKey, fn ($w) => $w->orWhere('auth_key', $authKey)))
                ->first();
            if (! $by) {
                $receipt->forceFill([
                    'verdict' => BoxOfficeReceipt::REJECTED,
                    'accepted' => false,
                    'rrn_key' => null,
                    'auth_key' => null,
                    'duplicate_of_order_id' => $other?->order_id,
                    'reasons' => array_merge($receipt->reasons ?? [], [['code' => 'duplicate_receipt', 'level' => 'reject', 'data' => ['reference' => $this->reference($other?->order_id)]]]),
                ])->save();

                return $order->fresh();
            }
            $receipt->forceFill(['accepted' => true, 'rrn_key' => null, 'auth_key' => null])->save();
        }

        return $this->pay($order, $receipt->fresh(), $by);
    }

    /** Create the card terminal payment and complete it through the normal fulfillment. */
    private function pay(BoxOfficeOrder $order, BoxOfficeReceipt $receipt, ?User $by): BoxOfficeOrder
    {
        $payment = DB::transaction(function () use ($order, $receipt, $by) {
            $locked = BoxOfficeOrder::withoutTenantScope()->with('lines')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [BoxOfficeOrder::STATUS_PENDING, BoxOfficeOrder::STATUS_EXPIRED], true)) {
                return null;
            }
            [$buyer, $tenant, $quote, $bookingDate, $meta] = $this->boxOffice->paymentInput($locked);
            $payment = $this->checkout->createPosPayment($buyer, $tenant, $quote, $bookingDate, "box_office:{$locked->id}:pos:{$receipt->id}", $meta + [
                'box_office_lang' => $locked->locale,
                'pos_receipt_id' => $receipt->id,
                'pos_verdict' => $receipt->verdict,
                'approved_by' => $by?->id,
            ]);
            $locked->payment_id = $payment->id;
            $locked->needs_review = $by === null && $receipt->verdict !== BoxOfficeReceipt::VERIFIED;
            $locked->save();

            return $payment;
        });

        if ($payment) {
            $this->fulfillment->complete($payment, [], 'card_pos', $by ? 'admin' : 'gate');
        }

        return $order->fresh();
    }

    // ------------------------------------------------------------------
    // Parsing helpers (public for tests)
    // ------------------------------------------------------------------

    /** "1.500" / "OMR 1,250.000" → minor units; null when missing or ambiguous ("1,500" without a decimal point). */
    public static function amountMinor(?string $amount, int $decimals): ?int
    {
        if ($amount === null || ! preg_match('/\d[\d,]*(?:\.\d+)?/', $amount, $m)) {
            return null;
        }
        $n = $m[0];
        if (str_contains($n, ',')) {
            if (! str_contains($n, '.')) {
                return null;
            }
            $n = str_replace(',', '', $n);
        }
        if (str_contains($n, '.') && strlen(substr($n, strpos($n, '.') + 1)) > $decimals) {
            return null;
        }

        return Money::toMinor($n, $decimals);
    }

    public static function date(?string $date): ?string
    {
        if ($date === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $m[0];
    }

    /** Upper case letters and digits only; null when shorter than $min. */
    public static function code(?string $value, int $min, int $max): ?string
    {
        $v = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        return strlen($v) >= $min ? substr($v, 0, $max) : null;
    }

    public static function last4(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    public static function currencyMatches(string $printed, string $currency): bool
    {
        $norm = fn (string $c) => mb_strtoupper((string) preg_replace('/[\s.]/u', '', $c));
        $aliases = array_map($norm, self::CURRENCY_ALIASES[strtoupper($currency)] ?? [$currency]);

        return in_array($norm($printed), $aliases, true);
    }

    /**
     * Keys that make a receipt usable once per organizer: the reference
     * number, and the approval code with terminal, amount and date.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function keys(array $c): array
    {
        $rrn = ! empty($c['rrn']) ? 'rrn:'.$c['rrn'] : null;
        $auth = ! empty($c['approval_code']) && ($c['amount_minor'] ?? null) !== null && ! empty($c['receipt_date'])
            ? 'auth:'.($c['terminal_id'] ?: '-').'|'.$c['approval_code'].'|'.$c['amount_minor'].'|'.$c['receipt_date']
            : null;

        return [$rrn, $auth];
    }

    /** The order's day and today, in the organizer's time zone (a sale just before midnight). */
    private function allowedDates(Tenant $tenant, BoxOfficeOrder $order): array
    {
        $zone = TenantTime::zone($tenant);

        return array_values(array_unique([
            $order->created_at->copy()->setTimezone($zone)->toDateString(),
            TenantTime::today($tenant),
        ]));
    }

    private function reference(?int $orderId): ?string
    {
        return $orderId ? BoxOfficeOrder::withoutTenantScope()->whereKey($orderId)->value('reference') : null;
    }
}

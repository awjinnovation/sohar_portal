<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\GateDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PublicApiCache;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes everything testers generated in ONE tenant and keeps its set-up.
 *
 * Kept: the tenant and its settings, every account that holds a role (admin
 * panel users, gate staff, vendor staff) and the platform super-admins,
 * events, ticket types, categories, tags, map locations, gates, vendors,
 * places, content, announcements, legal pages and public notifications.
 *
 * Deleted (this tenant only): visitors (accounts without any role), tickets,
 * payments and gateway records, wallets with their transactions / top-ups /
 * promo lots / deposits / refund requests / pay tokens, points, box office
 * orders, paired gate devices with their tokens, pairing codes and scans,
 * survey answers, AI conversations, personal notifications, favourites and
 * interests, OTP codes, the visitors' API tokens and the audit log.
 *
 *   php artisan tenant:wipe-test-data north-batinah --dry-run   # counts only
 *   php artisan tenant:wipe-test-data north-batinah             # asks to type the slug
 *   php artisan tenant:wipe-test-data north-batinah --force     # no question (scripts)
 *   php artisan tenant:wipe-test-data north-batinah --keep-event=1   # + delete every other event
 *
 * --keep-event also removes the tenant's other events (with their ticket
 * types, tags, legacy pricing and images); only the listed ids stay.
 *
 * Other tenants are never touched: a visitor who also has data in another
 * tenant keeps the account, only this tenant's rows go. Nothing here resets
 * counters — availability and "sold" figures are counted from tickets.
 */
class WipeTenantTestData extends Command
{
    protected $signature = 'tenant:wipe-test-data
        {tenant : Tenant slug, e.g. north-batinah}
        {--dry-run : Show what would be deleted and stop}
        {--force : Delete without asking for confirmation}
        {--keep-event= : Also delete every other event of the tenant; comma-separated ids of the events to keep (e.g. 1)}';

    protected $description = 'Delete the test data of one tenant (visitors, tickets, payments, wallets, scans…) and keep its events, staff and settings';

    public const AUDIT_ACTION = 'tenant.test_data_wiped';

    public const CONFIRM_QUESTION = 'This cannot be undone. Type the tenant slug to confirm';

    /** Tables whose rows of this tenant are deleted wholesale, children first. */
    private const TENANT_TABLES = [
        'gate_scans', 'gate_pairing_codes', 'gate_devices',
        'box_office_orders',
        'satisfaction_responses',
        'ai_messages', 'ai_plans', 'ai_conversations', 'ai_requests',
        'tickets',
        'payment_refunds', 'payment_events', 'payment_gateway_sessions', 'payment_gateway_calls', 'payment_webhook_events',
        'payments',
        'refund_requests', 'deposits', 'wallet_topups', 'wallet_promo_lots', 'pay_tokens', 'wallet_transactions', 'wallets',
        'points_transactions', 'points_accounts',
        'user_favorites', 'user_interests',
        'audit_logs',
    ];

    /** Tables that tell a visitor "belongs" to a tenant (used to protect other tenants' visitors). */
    private const FOOTPRINT = [
        'wallets' => 'user_id', 'points_accounts' => 'user_id', 'tickets' => 'user_id', 'payments' => 'user_id',
        'box_office_orders' => 'buyer_user_id', 'ai_conversations' => 'user_id', 'satisfaction_responses' => 'user_id',
        'user_favorites' => 'user_id', 'user_interests' => 'user_id', 'notifications' => 'user_id',
        'refund_requests' => 'user_id', 'deposits' => 'user_id',
    ];

    public function handle(): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->first();
        if (! $tenant) {
            $this->error('Unknown tenant: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        $this->line(sprintf('Environment <comment>%s</comment>   URL <comment>%s</comment>', app()->environment(), config('app.url')));
        $this->line(sprintf('Tenant <comment>%s</comment> (#%d, %s)', $tenant->slug, $tenant->id, $tenant->name['en'] ?? ''));

        $keep = $this->keptEvents($tenant);
        if ($keep === false) {
            return self::FAILURE;
        }
        if ($keep !== null) {
            $doomed = $this->eventsToDelete($tenant, $keep);
            $this->line('Events kept: '.$keep->map(fn (Event $e) => $this->describeEvent($e))->implode(', '));
            $this->line(sprintf('Events deleted (%d): %s', $doomed->count(), $doomed->map(fn (Event $e) => $this->describeEvent($e))->implode(', ') ?: 'none'));
        }
        $this->newLine();

        $plan = $this->plan($tenant, $keep);
        $this->table(['Data', 'Rows'], collect($plan)->map(fn ($n, $label) => [$label, number_format($n)])->values()->all());
        $this->line('Kept: tenant settings, accounts with a role, '.($keep === null ? 'events, ticket types' : 'the events above with their ticket types').', categories, gates, vendors, places, content, announcements, legal pages, public notifications.');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Refusing to delete without confirmation: re-run with --force, or in a terminal.');

                return self::FAILURE;
            }
            if ($this->ask(self::CONFIRM_QUESTION) !== $tenant->slug) {
                $this->warn('Aborted: the slug did not match.');

                return self::FAILURE;
            }
        }

        $deleted = DB::transaction(fn () => $this->wipe($tenant, $keep));
        PublicApiCache::bump($tenant->id);

        $this->table(['Data', 'Deleted'], collect($deleted)->map(fn ($n, $label) => [$label, number_format($n)])->values()->all());
        $this->info("Done: test data of tenant {$tenant->slug} removed; events, staff and settings kept.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Event>|null  $keep  events to keep (null = events are not touched)
     * @return array<string, int> label => rows that would be deleted
     */
    private function plan(Tenant $tenant, ?Collection $keep): array
    {
        $users = $this->visitorIds($tenant);
        $counts = [];
        foreach (self::TENANT_TABLES as $table) {
            $counts[$table] = $this->tenantRows($table, $tenant)->count();
        }
        if ($keep !== null) {
            $counts += $this->eventChildCounts($this->eventsToDelete($tenant, $keep));
        }
        $counts['box_office_order_lines'] = $this->orderLines($tenant)->count();
        $counts['notifications (personal)'] = $this->personalNotifications($tenant)->count();
        $counts['gate device tokens'] = $this->deviceTokens($tenant)->count();
        $counts['visitor API tokens'] = $this->userTokens($users)->count();
        $counts['otp_verifications'] = $this->otps($users)->count();
        $counts['users (visitors without a role)'] = count($users);

        return array_filter($counts, fn ($n) => $n > 0) ?: ['nothing' => 0];
    }

    /**
     * @param  Collection<int, Event>|null  $keep
     * @return array<string, int>
     */
    private function wipe(Tenant $tenant, ?Collection $keep): array
    {
        $users = $this->visitorIds($tenant);
        $phones = User::whereIn('id', $users)->whereNotNull('phone_number')->pluck('phone_number')->all();
        $deleted = [];

        $deleted['gate device tokens'] = $this->deviceTokens($tenant)->delete();
        $deleted['box_office_order_lines'] = $this->orderLines($tenant)->delete();
        foreach (self::TENANT_TABLES as $table) {
            $deleted[$table] = $this->tenantRows($table, $tenant)->delete();
        }
        if ($keep !== null) {
            // Tickets, orders and scans of every event are already gone; ticket
            // types, tags, pricing and places links cascade, images are removed
            // by the media library when the model is deleted.
            $doomed = $this->eventsToDelete($tenant, $keep);
            $deleted += $this->eventChildCounts($doomed);
            app(TenantContext::class)->run($tenant, fn () => $doomed->each(fn (Event $e) => $e->delete()));
        }
        $deleted['notifications (personal)'] = $this->personalNotifications($tenant)->delete();
        $deleted['otp_verifications'] = $this->otps($users, $phones)->delete();
        $deleted['visitor API tokens'] = $this->userTokens($users)->delete();
        // Cascades (DB): payments, wallets, points, favourites, interests,
        // notifications of these users in *this* tenant are already gone;
        // they have none in other tenants (see visitorIds).
        $deleted['users (visitors without a role)'] = User::whereIn('id', $users)->delete();

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'actor_id' => null,
            'action' => self::AUDIT_ACTION,
            'data' => ['deleted' => array_filter($deleted), 'command' => 'tenant:wipe-test-data'],
        ]);

        return array_filter($deleted, fn ($n) => $n > 0) ?: ['nothing' => 0];
    }

    /**
     * Events named by --keep-event: null when the option is absent, false
     * (after an error message) when an id is not an event of this tenant.
     *
     * @return Collection<int, Event>|null|false
     */
    private function keptEvents(Tenant $tenant): Collection|null|false
    {
        $raw = trim((string) $this->option('keep-event'));
        if ($raw === '') {
            return null;
        }
        $ids = collect(explode(',', $raw))->map(fn ($v) => (int) trim($v))->filter()->unique()->values();
        if ($ids->isEmpty()) {
            $this->error('--keep-event needs at least one event id.');

            return false;
        }
        $events = Event::withoutTenantScope()->where('tenant_id', $tenant->id)->whereIn('id', $ids)->get()->keyBy('id');
        $missing = $ids->reject(fn ($id) => $events->has($id));
        if ($missing->isNotEmpty()) {
            $this->error('Not an event of this tenant: #'.$missing->implode(', #'));

            return false;
        }

        return $events->values();
    }

    /** @return Collection<int, Event> */
    private function eventsToDelete(Tenant $tenant, Collection $keep): Collection
    {
        return Event::withoutTenantScope()->where('tenant_id', $tenant->id)->whereNotIn('id', $keep->pluck('id'))->orderBy('id')->get();
    }

    /** @return array<string, int> rows that go with these events */
    private function eventChildCounts(Collection $events): array
    {
        $ids = $events->pluck('id')->all() ?: [0];
        $counts = ['events' => count($events)];
        // Legacy tables (ticket_pricing) may have been dropped by a later migration.
        foreach (['event_ticket_types', 'event_tags', 'ticket_pricing', 'event_place'] as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->whereIn('event_id', $ids)->count();
            }
        }
        $counts['media (event images)'] = DB::table('media')->where('model_type', (new Event)->getMorphClass())->whereIn('model_id', $ids)->count();

        return $counts;
    }

    private function describeEvent(Event $event): string
    {
        $day = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v;
        $title = $event->title_ar ?: $event->title;

        return sprintf('#%d %s (%s → %s)', $event->id, $title, $day($event->start_time), $day($event->end_time));
    }

    private function tenantRows(string $table, Tenant $tenant): Builder
    {
        return DB::table($table)->where('tenant_id', $tenant->id);
    }

    private function orderLines(Tenant $tenant): Builder
    {
        return DB::table('box_office_order_lines')->whereIn('order_id', $this->tenantRows('box_office_orders', $tenant)->select('id'));
    }

    /** Visitor notifications; public ones (user_id null) are content and stay. */
    private function personalNotifications(Tenant $tenant): Builder
    {
        return $this->tenantRows('notifications', $tenant)->whereNotNull('user_id');
    }

    private function deviceTokens(Tenant $tenant): Builder
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new GateDevice)->getMorphClass())
            ->whereIn('tokenable_id', $this->tenantRows('gate_devices', $tenant)->select('id'));
    }

    /** @param  int[]  $users */
    private function userTokens(array $users): Builder
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $users ?: [0]);
    }

    /** OTP codes of the deleted visitors plus every expired code. */
    private function otps(array $users, ?array $phones = null): Builder
    {
        $phones ??= User::whereIn('id', $users)->whereNotNull('phone_number')->pluck('phone_number')->all();

        return DB::table('otp_verifications')->where(function (Builder $q) use ($phones) {
            $q->where('expires_at', '<', now());
            if ($phones) {
                $q->orWhereIn('phone_number', $phones);
            }
        });
    }

    /**
     * Accounts to delete: no role in any tenant, not a super-admin, not vendor
     * staff, and no data in any other tenant (users are global identities).
     * Accounts with no data anywhere (signed in, did nothing) are included.
     *
     * @return int[]
     */
    private function visitorIds(Tenant $tenant): array
    {
        $morph = (new User)->getMorphClass();

        $query = DB::table('users')
            ->where(fn (Builder $q) => $q->whereNull('is_super_admin')->orWhere('is_super_admin', false))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('model_has_roles')
                ->where('model_type', $morph)->whereColumn('model_id', 'users.id'))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('vendor_user')->whereColumn('vendor_user.user_id', 'users.id'));

        foreach (self::FOOTPRINT as $table => $column) {
            $query->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from($table)
                ->whereColumn("{$table}.{$column}", 'users.id')
                ->where(fn (Builder $w) => $w->where("{$table}.tenant_id", '!=', $tenant->id)->orWhereNull("{$table}.tenant_id")));
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}

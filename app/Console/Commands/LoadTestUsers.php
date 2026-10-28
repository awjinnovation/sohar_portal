<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Signed-in visitors for tests/load/visitors.js (staging / test servers only).
 * OTP sign-in is limited per IP, so a load test from one machine cannot log
 * in hundreds of users; this prints ready API tokens instead (JSON on stdout).
 *
 *   php artisan loadtest:users north-batinah 300 --balance=5000 > tokens.json
 *   php artisan loadtest:users north-batinah --revoke
 *
 * Users are 96800000000, 96800000001, … ("Load test N"); running it again
 * reuses them and adds a fresh token each.
 */
class LoadTestUsers extends Command
{
    // 968 + 00…: not an Omani mobile range, so no SMS can ever reach a person.
    public const PHONE_PREFIX = '96800';

    public const TOKEN_NAME = 'loadtest';

    protected $signature = 'loadtest:users {tenant : Tenant slug} {count=300 : Number of users} {--balance=0 : Wallet credit per user, minor units (once per user)} {--revoke : Delete every load-test token instead}';

    protected $description = 'Create load-test users with API tokens (never in production)';

    public function handle(TenantContext $context, WalletService $wallets): int
    {
        if (app()->environment('production')) {
            $this->error('Refused: APP_ENV=production. Run it on a staging / test server.');

            return self::FAILURE;
        }

        $users = User::where('phone_number', 'like', self::PHONE_PREFIX.'%');
        if ($this->option('revoke')) {
            $deleted = 0;
            $users->each(function (User $u) use (&$deleted) {
                $deleted += $u->tokens()->where('name', self::TOKEN_NAME)->delete();
            });
            $this->info("Deleted {$deleted} load-test tokens.");

            return self::SUCCESS;
        }

        $tenant = Tenant::findBySlug($this->argument('tenant'));
        if (! $tenant) {
            $this->error('Unknown tenant: '.$this->argument('tenant'));

            return self::FAILURE;
        }

        $count = max(1, min(5000, (int) $this->argument('count')));
        $balance = max(0, (int) $this->option('balance'));

        $tokens = $context->run($tenant, function () use ($count, $balance, $tenant, $wallets) {
            $tokens = [];
            for ($i = 0; $i < $count; $i++) {
                $user = User::firstOrCreate(
                    ['phone_number' => self::PHONE_PREFIX.str_pad((string) $i, 6, '0', STR_PAD_LEFT)],
                    ['name' => 'Load test '.$i, 'email' => null, 'password' => Hash::make(Str::random(32)), 'phone_verified_at' => now()],
                );
                if ($balance > 0) {
                    $wallets->credit($wallets->walletFor($user, $tenant), $balance, 'adjustment', [
                        'bucket' => 'cash',
                        'idempotency_key' => "loadtest:{$tenant->id}:{$user->id}",
                        'meta' => ['reason' => 'load test'],
                    ]);
                }
                $tokens[] = $user->createToken(self::TOKEN_NAME)->plainTextToken;
            }

            return $tokens;
        });

        // Only the JSON goes to stdout, so it can be redirected to a file.
        $this->line(json_encode($tokens));
        $this->output->getErrorStyle()->writeln(count($tokens)." load-test tokens for {$tenant->slug}".($balance ? ", wallet credit {$balance} each" : ''));

        return self::SUCCESS;
    }
}

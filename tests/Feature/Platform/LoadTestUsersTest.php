<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\PlatformHelpers;
use Tests\TestCase;

/** php artisan loadtest:users (tests/load/visitors.js signed-in visitors). */
class LoadTestUsersTest extends TestCase
{
    use PlatformHelpers, RefreshDatabase;

    private function tokens(array $args): array
    {
        $this->assertSame(0, Artisan::call('loadtest:users', $args));

        return json_decode(strtok(Artisan::output(), "\n"), true);
    }

    #[Test]
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame(1, Artisan::call('loadtest:users', ['tenant' => $this->tenant->slug, 'count' => 2]));
        $this->assertSame(0, User::where('phone_number', 'like', '96800%')->count());
    }

    #[Test]
    public function tokens_sign_in_users_with_their_wallet_credit_given_once(): void
    {
        $tokens = $this->tokens(['tenant' => $this->tenant->slug, 'count' => 3, '--balance' => 5000]);
        $this->assertCount(3, $tokens);

        $this->getJson('/api/v1/wallet', $this->tenantHeaders(null, ['Authorization' => "Bearer {$tokens[2]}"]))
            ->assertOk()->assertJsonPath('data.balance_minor', 5000);

        // Again: same users, a new token each, no second credit.
        $again = $this->tokens(['tenant' => $this->tenant->slug, 'count' => 3, '--balance' => 5000]);
        $this->assertNotSame($tokens[0], $again[0]);
        $this->assertSame(3, User::where('phone_number', 'like', '96800%')->count());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/wallet', $this->tenantHeaders(null, ['Authorization' => "Bearer {$again[2]}"]))
            ->assertOk()->assertJsonPath('data.balance_minor', 5000);
    }

    #[Test]
    public function revoke_deletes_only_load_test_tokens(): void
    {
        $tokens = $this->tokens(['tenant' => $this->tenant->slug, 'count' => 2]);
        $other = $this->makeUser()->createToken('app')->plainTextToken;

        $this->assertSame(0, Artisan::call('loadtest:users', ['tenant' => $this->tenant->slug, '--revoke' => true]));

        $this->getJson('/api/v1/wallet', $this->tenantHeaders(null, ['Authorization' => "Bearer {$tokens[0]}"]))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/wallet', $this->tenantHeaders(null, ['Authorization' => "Bearer $other"]))->assertOk();
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Exception\MissingInputException;

/**
 * Create (or promote) a platform super-admin with a strong password.
 *
 *   php artisan platform:create-admin ops@awj.om                 # prompts (hidden, twice)
 *   php artisan platform:create-admin ops@awj.om --generate      # prints a random 24-char password once
 *   php artisan platform:create-admin ops@awj.om --generate --lock-seeded
 *
 * --lock-seeded replaces the password of every account that still uses the
 * seeded default ("password123", see database/seeders) with a random one
 * nobody knows; an admin can set a new one later in Admin → Users.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:create-admin
        {email : E-mail address used to sign in to /admin}
        {--name= : Display name (default "Platform Admin", or the existing name)}
        {--generate : Generate a random password instead of prompting}
        {--lock-seeded : Also replace the seeded default password on every account that still has it}';

    protected $description = 'Create or promote a platform super-admin with a strong password';

    public const MIN_LENGTH = 12;

    private const SEEDED_PASSWORD = 'password123';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not a valid e-mail address: {$email}");

            return self::FAILURE;
        }

        $password = $this->option('generate') ? Str::password(24, symbols: false) : $this->askPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        $created = ! $user;
        $user ??= new User(['email' => $email]);

        $user->name = $this->option('name') ?: ($user->name ?: 'Platform Admin');
        $user->password = $password; // "hashed" cast
        $user->forceFill([
            'is_super_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info(($created ? 'Created' : 'Updated').' platform super-admin '.$email.' (id '.$user->id.').');
        if ($this->option('generate')) {
            $this->line('Password (shown once, store it in your password manager):');
            $this->line('  '.$password);
        }

        if ($this->option('lock-seeded')) {
            $this->lockSeededAccounts($user);
        }

        return self::SUCCESS;
    }

    private function askPassword(): ?string
    {
        $noTerminal = 'No terminal to prompt for a password: run it with "docker compose exec" / "docker exec -it", or pass --generate.';
        if (! $this->input->isInteractive()) {
            $this->error($noTerminal);

            return null;
        }

        try {
            for ($try = 1; $try <= 3; $try++) {
                $password = (string) $this->secret('Password (min '.self::MIN_LENGTH.' characters)');
                $problem = $this->weakness($password);
                if ($problem) {
                    $this->warn($problem);

                    continue;
                }
                if ($password !== (string) $this->secret('Repeat the password')) {
                    $this->warn('The passwords do not match.');

                    continue;
                }

                return $password;
            }
        } catch (MissingInputException) {
            // stdin closed (no TTY attached, e.g. `docker exec` without -it).
            $this->newLine();
            $this->error($noTerminal);

            return null;
        }

        $this->error('No password set.');

        return null;
    }

    private function weakness(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Too short: use at least '.self::MIN_LENGTH.' characters.';
        }
        if (strcasecmp($password, self::SEEDED_PASSWORD) === 0 || count(array_unique(mb_str_split($password))) < 5) {
            return 'Too easy to guess: pick something longer and less repetitive.';
        }

        return null;
    }

    private function lockSeededAccounts(User $keep): void
    {
        $locked = 0;
        // Only e-mail accounts can sign in with a password (/admin); phone
        // visitors use OTP and are skipped.
        User::whereNotNull('email')->whereNotNull('password')->whereKeyNot($keep->getKey())->orderBy('id')
            ->each(function (User $u) use (&$locked) {
                try {
                    $seeded = Hash::check(self::SEEDED_PASSWORD, $u->password);
                } catch (\RuntimeException) {
                    $seeded = false; // not a bcrypt hash
                }
                if ($seeded) {
                    $u->forceFill(['password' => Str::password(40), 'remember_token' => Str::random(60)])->save();
                    $this->line('  locked '.$u->email);
                    $locked++;
                }
            });

        $this->info("Replaced the seeded default password on {$locked} account(s).");
    }
}

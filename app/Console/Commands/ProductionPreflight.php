<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ArabicDate;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionPreflight extends Command
{
    protected $signature = 'production:preflight';

    protected $description =
        'Run production-readiness checks before exposing the ERP to real users.';

    private array $rows = [];

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(
        Migrator $migrator
    ): int {
        $this->components->info(
            'Dahab ERP production preflight'
        );

        $this->checkEnvironment();
        $this->checkSecurityConfiguration();
        $this->checkDatabase($migrator);
        $this->checkDemoCredentials();
        $this->checkStorage();
        $this->checkOperationalServices();

        $this->newLine();

        $this->table(
            ['Status', 'Check', 'Details'],
            $this->rows
        );

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error(
                sprintf(
                    'NOT READY: %d blocker(s), %d warning(s).',
                    $this->failures,
                    $this->warnings
                )
            );

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->components->warn(
                sprintf(
                    'READY WITH WARNINGS: %d warning(s). Review them before launch.',
                    $this->warnings
                )
            );

            return self::SUCCESS;
        }

        $this->components->info(
            'READY: all automated production checks passed.'
        );

        return self::SUCCESS;
    }

    private function checkEnvironment(): void
    {
        $this->assert(
            app()->environment('production'),
            'APP_ENV',
            'production',
            'APP_ENV must be production.'
        );

        $this->assert(
            config('app.debug') === false,
            'APP_DEBUG',
            'disabled',
            'APP_DEBUG must be false.'
        );

        $key = trim(
            (string) config('app.key')
        );

        $this->assert(
            $key !== '',
            'APP_KEY',
            'configured',
            'APP_KEY is empty.'
        );

        $url = trim(
            (string) config('app.url')
        );

        $this->assert(
            str_starts_with(
                strtolower($url),
                'https://'
            ),
            'APP_URL / HTTPS',
            $url !== '' ? $url : 'missing',
            'Production APP_URL must use HTTPS.'
        );

        $timezone = ArabicDate::timezone();

        $this->assert(
            in_array(
                $timezone,
                timezone_identifiers_list(),
                true
            ),
            'Timezone',
            $timezone,
            'Configured timezone is invalid.'
        );
    }

    private function checkSecurityConfiguration(): void
    {
        $secureCookie =
            (bool) config(
                'session.secure',
                false
            );

        $this->assert(
            $secureCookie,
            'Secure session cookie',
            $secureCookie ? 'enabled' : 'disabled',
            'Set SESSION_SECURE_COOKIE=true for HTTPS production.'
        );

        $httpOnly =
            (bool) config(
                'session.http_only',
                true
            );

        $this->assert(
            $httpOnly,
            'HTTP-only session cookie',
            $httpOnly ? 'enabled' : 'disabled',
            'Session cookies should be HTTP-only.'
        );

        $sameSite = strtolower(
            (string) config(
                'session.same_site',
                'lax'
            )
        );

        $this->assert(
            in_array(
                $sameSite,
                ['lax', 'strict'],
                true
            ),
            'Session SameSite',
            $sameSite !== ''
                ? $sameSite
                : 'missing',
            'Use SESSION_SAME_SITE=lax or strict.'
        );
    }

    private function checkDatabase(
        Migrator $migrator
    ): void {
        try {
            DB::connection()->getPdo();

            $this->pass(
                'Database connection',
                DB::connection()->getDatabaseName()
            );
        } catch (Throwable $e) {
            $this->recordFailure(
                'Database connection',
                $e->getMessage()
            );

            return;
        }

        if (! Schema::hasTable('migrations')) {
            $this->recordFailure(
                'Migrations table',
                'migrations table is missing.'
            );

            return;
        }

        try {
            $files =
                $migrator->getMigrationFiles(
                    database_path('migrations')
                );

            $ran =
                $migrator
                    ->getRepository()
                    ->getRan();

            $pending = array_values(
                array_diff(
                    array_keys($files),
                    $ran
                )
            );

            if ($pending === []) {
                $this->pass(
                    'Pending migrations',
                    'none'
                );
            } else {
                $this->recordFailure(
                    'Pending migrations',
                    implode(', ', $pending)
                );
            }
        } catch (Throwable $e) {
            $this->recordFailure(
                'Pending migrations',
                $e->getMessage()
            );
        }

        foreach ([
            'users',
            'locations',
            'employees',
            'orders',
            'payments',
            'invoices',
            'inventories',
            'special_cake_orders',
            'notifications',
        ] as $table) {
            $this->assert(
                Schema::hasTable($table),
                "Core table: {$table}",
                'present',
                "Required table {$table} is missing."
            );
        }
    }

    private function checkDemoCredentials(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $knownCredentials = [
            'admin' => 'Admin@2024!',
            'factory_mgr' => 'Factory@2024!',
            'branch_b01' => 'Branch@2024!',
            'branch_b02' => 'Branch@2024!',
            'branch_b03' => 'Branch@2024!',
            'cashier_b01' => 'Cashier@2024!',
            'cashier_b02' => 'Cashier@2024!',
            'accountant' => 'Accountant@2024!',
            'inventory_mgr' => 'Inventory@2024!',
            'cake_designer' => 'Designer@2024!',
        ];

        $compromised = [];

        foreach (
            $knownCredentials
            as $username => $password
        ) {
            $user = User::query()
                ->where(
                    'username',
                    $username
                )
                ->first();

            if (
                $user
                && Hash::check(
                    $password,
                    (string) $user->password
                )
            ) {
                $compromised[] =
                    $username;
            }
        }

        if ($compromised === []) {
            $this->pass(
                'Known demo passwords',
                'none detected'
            );

            return;
        }

        $this->recordFailure(
            'Known demo passwords',
            'Rotate immediately: '
                . implode(
                    ', ',
                    $compromised
                )
        );
    }

    private function checkStorage(): void
    {
        $this->assert(
            is_writable(
                storage_path()
            ),
            'storage/ writable',
            storage_path(),
            'Laravel storage directory is not writable.'
        );

        $this->assert(
            is_writable(
                base_path(
                    'bootstrap/cache'
                )
            ),
            'bootstrap/cache writable',
            base_path(
                'bootstrap/cache'
            ),
            'bootstrap/cache is not writable.'
        );

        $publicStorage =
            public_path('storage');

        $this->assert(
            file_exists($publicStorage),
            'Public storage link',
            $publicStorage,
            'Run php artisan storage:link.'
        );
    }

    private function checkOperationalServices(): void
    {
        $mailer = (string) config(
            'mail.default',
            'log'
        );

        if ($mailer === 'log') {
            $this->warn(
                'Mail transport',
                'MAIL_MAILER=log; scheduled reports will not reach real recipients.'
            );
        } else {
            $this->pass(
                'Mail transport',
                $mailer
            );
        }

        $queue = (string) config(
            'queue.default',
            'sync'
        );

        if ($queue === 'sync') {
            $this->warn(
                'Queue',
                'sync; slow external work will run inside the web request.'
            );
        } else {
            $this->pass(
                'Queue',
                $queue
            );
        }

        $logLevel = strtolower(
            (string) config(
                'logging.level',
                'debug'
            )
        );

        if ($logLevel === 'debug') {
            $this->warn(
                'Log level',
                'debug; prefer info/warning in production.'
            );
        } else {
            $this->pass(
                'Log level',
                $logLevel
            );
        }

        $this->warn(
            'Scheduler cron',
            'Verify: * * * * * php artisan schedule:run'
        );

        $this->warn(
            'Backups',
            'Verify an off-server database + uploaded-files backup before launch.'
        );
    }

    private function assert(
        bool $condition,
        string $check,
        string $successDetails,
        string $failureDetails
    ): void {
        if ($condition) {
            $this->pass(
                $check,
                $successDetails
            );

            return;
        }

        $this->recordFailure(
            $check,
            $failureDetails
        );
    }

    private function pass(
        string $check,
        string $details
    ): void {
        $this->rows[] = [
            'PASS',
            $check,
            $details,
        ];
    }

    private function warn(
        string $check,
        string $details
    ): void {
        $this->warnings++;

        $this->rows[] = [
            'WARN',
            $check,
            $details,
        ];
    }

    private function recordFailure(
        string $check,
        string $details
    ): void {
        $this->failures++;

        $this->rows[] = [
            'FAIL',
            $check,
            $details,
        ];
    }
}

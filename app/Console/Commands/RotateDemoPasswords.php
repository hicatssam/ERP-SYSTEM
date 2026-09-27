<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RotateDemoPasswords extends Command
{
    protected $signature =
        'security:rotate-demo-passwords
        {--force : Skip the confirmation prompt}';

    protected $description =
        'Rotate only users that still use the known demo passwords.';

    public function handle(): int
    {
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

        $vulnerable = collect(
            $knownCredentials
        )
            ->mapWithKeys(
                function (
                    string $password,
                    string $username
                ): array {
                    $user = User::query()
                        ->where(
                            'username',
                            $username
                        )
                        ->first();

                    if (
                        ! $user
                        || ! Hash::check(
                            $password,
                            (string) $user->password
                        )
                    ) {
                        return [];
                    }

                    return [
                        $username => $user,
                    ];
                }
            );

        if ($vulnerable->isEmpty()) {
            $this->components->info(
                'No users are using the known demo passwords.'
            );

            return self::SUCCESS;
        }

        $this->warn(
            'The following accounts still use public demo passwords: '
            . $vulnerable->keys()->implode(', ')
        );

        if (
            ! $this->option('force')
            && ! $this->confirm(
                'Rotate these passwords now?',
                true
            )
        ) {
            $this->components->warn(
                'No passwords were changed.'
            );

            return self::SUCCESS;
        }

        $rows = [];

        foreach (
            $vulnerable
            as $username => $user
        ) {
            $temporary =
                Str::password(
                    length: 18,
                    letters: true,
                    numbers: true,
                    symbols: true,
                    spaces: false
                );

            $user->forceFill([
                'password' =>
                    Hash::make($temporary),
                'must_change_password' =>
                    true,
                'failed_login_attempts' =>
                    0,
                'login_locked_until' =>
                    null,
            ])->save();

            $rows[] = [
                $username,
                $temporary,
                'Yes',
            ];
        }

        $this->newLine();

        $this->table(
            [
                'Username',
                'Temporary password',
                'Must change',
            ],
            $rows
        );

        $this->newLine();

        $this->components->warn(
            'Copy these temporary passwords now. '
            . 'They are not stored in plaintext anywhere.'
        );

        return self::SUCCESS;
    }
}

<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        // Create a minimal Employee record so the NOT NULL FK is satisfied.
        $employee = Employee::create([
            'employee_number'   => 'EMP-' . Str::random(6),
            'full_name'         => fake()->name(),
            'employment_status' => 'active',
        ]);

        return [
            'employee_id'          => $employee->id,
            'username'             => fake()->unique()->userName(),
            'email'                => fake()->unique()->safeEmail(),
            'password'             => static::$password ??= Hash::make('password'),
            'is_active'            => true,
            'must_change_password' => false,
            'remember_token'       => Str::random(10),
        ];
    }
}

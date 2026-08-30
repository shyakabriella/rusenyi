<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Default user.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),

            'email' => fake()
                ->unique()
                ->safeEmail(),

            'phone' => '07' . fake()
                ->unique()
                ->numerify('########'),

            'role' => User::ROLE_AGENT,

            'status' => 'active',

            'is_active' => true,

            'must_change_password' => false,

            'email_verified_at' => now(),

            // User model automatically hashes this
            'password' => 'Gihombo@123',

            'remember_token' => Str::random(10),

            'last_login_at' => null,
        ];
    }

    /**
     * Admin user.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /**
     * Accountant user.
     */
    public function accountant(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_ACCOUNTANT,
        ]);
    }

    /**
     * Balance Officer user.
     */
    public function balance(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_BALANCE,
        ]);
    }

    /**
     * Agent user.
     */
    public function agent(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_AGENT,
        ]);
    }

    /**
     * Driver user.
     */
    public function driver(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_DRIVER,
        ]);
    }

    /**
     * Store Officer user.
     *
     * We use storeOfficer() instead of store()
     * because Laravel Factory already has a store() method.
     */
    public function storeOfficer(): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_STORE,
        ]);
    }

    /**
     * Inactive account.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    /**
     * Suspended account.
     */
    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => 'suspended',
            'is_active' => false,
        ]);
    }

    /**
     * User must change temporary password.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn () => [
            'must_change_password' => true,
        ]);
    }
}

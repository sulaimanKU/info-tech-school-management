<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Test Staff', 'email' => fake()->unique()->userName().'@example.test', 'password' => bin2hex(random_bytes(16)), 'is_active' => true];
    }
}

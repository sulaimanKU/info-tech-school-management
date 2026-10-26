<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CreateSuperAdmin extends Command
{
    protected $signature = 'school:create-super-admin';

    protected $description = 'Interactively create a Super Admin / Managing Director using existing school RBAC';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('An interactive terminal is required. Passwords cannot be supplied as command arguments.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $this->info('Use a password of 12 to 1024 characters, matching the existing staff password policy.');

        try {
            // Never fall back to visible input when the terminal cannot hide passwords.
            $password = $this->secret('Password', false);
            $confirmation = $this->secret('Password confirmation', false);
        } catch (RuntimeException) {
            $this->error('Hidden password input is unavailable. Use an interactive terminal.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => 'required|string|max:200',
            'email' => 'required|email|max:254|unique:users,email',
            'password' => 'required|string|min:12|max:1024|confirmed',
            'password_confirmation' => 'required|string|max:1024',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($name, $email, $password) {
                $role = Role::where('code', 'super_admin')->lockForUpdate()->firstOrFail();
                // User's existing hashed cast owns password hashing and UUID generation.
                $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
                $user->roles()->attach($role);
                // The actor is the trusted CLI operator, not an authenticated application user.
                Audit::record('super_admin_account_created', null, 'users', $user->id);
            });
        } catch (ModelNotFoundException) {
            $this->error('The existing super_admin role is missing. Run the project role/reference seeders first. No account was created.');

            return self::FAILURE;
        } catch (QueryException $e) {
            // In particular, handle concurrent duplicate creation without printing SQL/bindings.
            $this->error(($e->errorInfo[0] ?? null) === '23505'
                ? 'The email has already been taken. No account was created.'
                : 'The database operation failed. No account was created.');

            return self::FAILURE;
        }

        $this->info('Super Admin / Managing Director account created successfully.');

        return self::SUCCESS;
    }
}

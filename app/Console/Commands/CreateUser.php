<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    /**
     * Artisan signature for the user:create command, including the --admin option.
     *
     * @var string
     */
    protected $signature = 'user:create {--admin : Grant the new user admin privileges}';

    /**
     * One-line description shown in the `php artisan list` output.
     *
     * @var string
     */
    protected $description = 'Create a new user account';

    /**
     * Create a new user interactively, prompting for name, email, and password.
     *
     * @return int Exit code.
     */
    public function handle(): int
    {
        $name = $this->ask(__('Name'));

        $email = $this->ask(__('Email'));

        $validator = Validator::make(['email' => $email], ['email' => 'required|email|unique:users,email']);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $password = $this->secret(__('Password'));
        $confirm = $this->secret(__('Confirm password'));

        if ($password !== $confirm) {
            $this->error(__('Passwords do not match.'));

            return self::FAILURE;
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->email_verified_at = now();
        $user->password = Hash::make($password);
        $user->is_admin = $this->option('admin');
        $user->save();

        $this->info(__('User created successfully.'));

        if ($user->is_admin) {
            $this->line(__('Admin privileges granted.'));
        }

        return self::SUCCESS;
    }
}

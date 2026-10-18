<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** First admin account for /admin (more admins can then be added from Settings). */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email?} {--first=} {--last=}';

    protected $description = 'Create an admin panel account';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email') ?: text('Email', required: true)));
        $first = $this->option('first') ?: text('First name', default: 'Admin', required: true);
        $last = $this->option('last') ?: text('Last name', default: 'User', required: true);
        $pass = password('Password (min 10 characters, letters and numbers)', required: true);

        $validator = Validator::make(compact('email', 'pass'), [
            'email' => ['required', 'email', 'unique:users,email'],
            'pass' => ['required', Password::min(10)->letters()->numbers()],
        ], ['email.unique' => 'An account with this email already exists.']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'role' => User::ROLE_ADMIN, 'first_name' => $first, 'last_name' => $last,
            'email' => $email, 'password' => $pass, 'email_verified_at' => now(),
        ]);
        $this->info('Admin created. Sign in at '.url('/admin')." as $email.");

        return self::SUCCESS;
    }
}

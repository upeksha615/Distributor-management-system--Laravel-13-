<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** First Super Admin for a new installation: php artisan user:create-admin */
class CreateAdmin extends Command
{
    protected $signature = 'user:create-admin {--name= : Full name} {--email= : Login email} {--password= : Password (leave out to be asked privately)}';

    protected $description = 'Create a Super Admin user (or make an existing user Super Admin and set a new password)';

    public function handle(): int
    {
        Permissions::sync(); // roles and permissions must exist

        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Login email');
        $password = $this->option('password') ?: $this->secret('Password (min. 8 characters, not shown)');
        if (! $this->option('password') && $password !== $this->secret('Repeat the password')) {
            $this->error('The passwords do not match. Nothing was changed.');

            return self::FAILURE;
        }

        $validator = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing && ! $this->confirm("{$email} already exists. Make it Super Admin and set this password?", true)) {
            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name, 'password' => $password, 'role' => 'super_admin', 'is_active' => true,
        ]);
        \App\Models\AuditLog::record('user.create_admin', $user, "Super Admin {$email} ".($existing ? 'updated' : 'created').' from the command line.');

        $this->info("Super Admin ready: {$email}. Log in with this email and the password you entered.");

        if ($email !== 'admin@example.com' && ($demo = User::where('email', 'admin@example.com')->where('is_active', true)->first())) {
            if ($this->confirm('The default account admin@example.com (password "password") is still active. Disable it now?', true)) {
                $demo->update(['is_active' => false]);
                $this->info('admin@example.com disabled.');
            }
        }

        return self::SUCCESS;
    }
}

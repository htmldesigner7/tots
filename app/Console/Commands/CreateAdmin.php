<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'cms:create-admin {--name= : Full name} {--email= : Email address} {--role=super-admin : super-admin, admin or editor} {--password= : Password (prompted securely when omitted)}';

    protected $description = 'Create a CMS user (the first account should be a Super Administrator)';

    public function handle(): int
    {
        $role = Role::where('slug', $this->option('role'))->first();
        if (! $role) {
            $this->error('Unknown role. Run `php artisan db:seed` first, then use super-admin, admin or editor.');

            return self::FAILURE;
        }
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password (min. 12 characters, upper/lower case and a number)');

        $v = Validator::make(compact('name', 'email', 'password'), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role_id' => $role->id, 'is_active' => true]);
        $this->info("Created {$role->name} {$user->email}. Sign in at ".route('admin.login'));

        return self::SUCCESS;
    }
}

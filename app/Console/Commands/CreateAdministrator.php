<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('bmi:create-admin {--name= : Full name} {--email= : Login email}')]
#[Description('Create a Super Administrator account (use this instead of demo accounts in production)')]
class CreateAdministrator extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $secret = password('Password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => strtolower($email), 'password' => $secret]);
        $user->forceFill(['role' => Role::SuperAdmin, 'is_active' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Super Administrator {$user->email} created.");

        return self::SUCCESS;
    }
}

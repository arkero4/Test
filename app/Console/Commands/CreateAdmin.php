<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'orchestrator:create-admin {email}';

    protected $description = 'Create or reset the local administrator account';

    public function handle(): int
    {
        $password = $this->secret('Password (minimum 12 characters)');
        if (! $password || strlen($password) < 12) {
            $this->error('Password too short.');

            return self::FAILURE;
        }
        User::updateOrCreate(['email' => $this->argument('email')], ['name' => 'Administrator', 'password' => Hash::make($password)]);
        $this->info('Administrator ready.');

        return self::SUCCESS;
    }
}

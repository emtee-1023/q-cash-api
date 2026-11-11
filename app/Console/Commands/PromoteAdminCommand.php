<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:promote {phone : Phone number of an existing user}')]
#[Description('Promote an existing user to an activated admin (bootstrap only)')]
class PromoteAdminCommand extends Command
{
    public function handle(): int
    {
        $phoneNumber = PhoneNumber::normalize((string) $this->argument('phone'));

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->first();

        if (! $user) {
            $this->error("No user found for phone number {$phoneNumber}.");

            return self::FAILURE;
        }

        $user->forceFill([
            'is_admin' => true,
            'admin_activated_at' => now(),
        ])->save();

        $this->info("{$user->name} is now an activated admin.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Organisation;
use App\Models\User;
use App\Services\UserAccess;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Creates the first Compass admin and prints their invite link. Run once after deploying. */
class InviteAdmin extends Command
{
    protected $signature = 'portal:invite-admin {email} {name}';
    protected $description = 'Invite a Compass MEP admin and print the invite link';

    public function handle(): int
    {
        $account = Account::where('slug', 'compass-mep')->first();
        if (! $account) {
            $this->error('Run "php artisan db:seed --force" first.');

            return self::FAILURE;
        }
        $org = Organisation::withoutGlobalScopes()->where('account_id', $account->id)->where('type', 'compass')->firstOrFail();
        $email = Str::lower($this->argument('email'));
        $user = User::withoutGlobalScopes()->firstOrNew(['email' => $email]);
        if ($user->exists && $user->status === User::ACTIVE) {
            $this->error("{$email} already has an active account.");

            return self::FAILURE;
        }
        $user->fill(['account_id' => $account->id, 'organisation_id' => $org->id, 'name' => $this->argument('name'), 'role' => Roles::ADMIN, 'status' => User::INVITED])->save();
        $link = UserAccess::sendInvite($user);
        $this->info("Invite created for {$email}. It is valid for 7 days:");
        $this->line($link);

        return self::SUCCESS;
    }
}

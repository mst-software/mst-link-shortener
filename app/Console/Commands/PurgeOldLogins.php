<?php

namespace App\Console\Commands;

use App\Models\UserLogin;
use App\Services\Settings;
use Illuminate\Console\Command;

class PurgeOldLogins extends Command
{
    protected $signature = 'logins:purge';

    protected $description = 'Delete sign-up and sign-in footprints older than the login retention period';

    public function handle(): int
    {
        $days = max(1, Settings::int('login_purge_days'));
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $ids = UserLogin::query()->where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : UserLogin::query()->whereKey($ids)->delete();
        } while ($ids->count() === 1000);

        $this->info('Purged '.$deleted.' login footprints.');

        return self::SUCCESS;
    }
}

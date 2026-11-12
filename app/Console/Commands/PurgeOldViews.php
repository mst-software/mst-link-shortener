<?php

namespace App\Console\Commands;

use App\Enums\ViewStatus;
use App\Models\View;
use App\Services\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class PurgeOldViews extends Command
{
    protected $signature = 'views:purge';

    protected $description = 'Delete aggregated raw views older than the retention period (never pending ones)';

    public function handle(): int
    {
        $cutoff = now()->subDays(Settings::int('view_purge_days'));
        $deleted = 0;

        View::query()
            ->whereNotNull('aggregated_at')
            ->where('status', '!=', ViewStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->select('id')
            ->chunkById(1000, function (Collection $chunk) use (&$deleted): void {
                $deleted += View::query()
                    ->whereKey($chunk->modelKeys())
                    ->whereNotNull('aggregated_at')
                    ->where('status', '!=', ViewStatus::Pending)
                    ->delete();
            });

        $this->info('Purged '.$deleted.' views.');

        return self::SUCCESS;
    }
}

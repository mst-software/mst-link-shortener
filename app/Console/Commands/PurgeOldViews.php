<?php

namespace App\Console\Commands;

use App\Enums\ViewStatus;
use App\Models\CodeAttempt;
use App\Models\View;
use App\Models\ViewDedup;
use App\Models\VisitSession;
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
            ->where('status', '!=', ViewStatus::Pending)
            ->whereNull('campaign_id')
            ->where('created_at', '<', $cutoff)
            ->where(function ($query): void {
                // Aggregated views, plus invalid/cancelled ones that are never aggregated.
                $query->whereNotNull('aggregated_at')
                    ->orWhereIn('status', [ViewStatus::Invalid, ViewStatus::Cancelled]);
            })
            ->select('id')
            ->chunkById(1000, function (Collection $chunk) use (&$deleted): void {
                $deleted += View::query()
                    ->whereKey($chunk->modelKeys())
                    ->where('status', '!=', ViewStatus::Pending)
                    ->delete();
            });

        // Unused code attempts are useless a day after they expired (or after they started without ever
        // getting a code); used ones are kept as long as the views they paid for.
        $attemptCutoff = now()->subDay();
        CodeAttempt::query()
            ->whereNull('used_at')
            ->where(function ($query) use ($attemptCutoff): void {
                $query->where('expires_at', '<', $attemptCutoff)
                    ->orWhere(fn ($unissued) => $unissued->whereNull('expires_at')->where('started_at', '<', $attemptCutoff));
            })
            ->delete();
        CodeAttempt::query()->whereNotNull('used_at')->where('used_at', '<', $cutoff)->delete();

        // Expired dedup keys and finished or expired visit sessions are useless after their window.
        ViewDedup::query()->where('expires_at', '<', now())->delete();
        VisitSession::query()->where('expires_at', '<', now()->subDay())->whereDoesntHave('view')->delete();

        $this->info('Purged '.$deleted.' views.');

        return self::SUCCESS;
    }
}

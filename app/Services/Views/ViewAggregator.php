<?php

namespace App\Services\Views;

use App\Enums\ViewStatus;
use App\Models\DailyStat;
use App\Models\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ViewAggregator
{
    private const CHUNK_SIZE = 1000;

    /**
     * Fold valid (pending or released) views that are not yet aggregated into
     * daily_stats per user and UTC day, then mark them. Safe to run repeatedly:
     * every view is counted exactly once. Returns the number of views folded.
     */
    public function aggregate(): int
    {
        $total = 0;

        View::query()
            ->whereNull('aggregated_at')
            ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use (&$total): void {
                $total += $this->foldChunk($chunk->modelKeys());
            });

        return $total;
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function foldChunk(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            // Re-read under lock so a concurrent run cannot count the same rows twice.
            $views = View::query()
                ->whereKey($ids)
                ->whereNull('aggregated_at')
                ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
                ->lockForUpdate()
                ->get(['id', 'user_id', 'earn_micro', 'created_at']);

            if ($views->isEmpty()) {
                return 0;
            }

            $groups = $views->groupBy(fn (View $view): string => $view->user_id.'|'.$view->created_at->copy()->utc()->toDateString());

            foreach ($groups as $key => $group) {
                [$userId, $date] = explode('|', (string) $key);

                DailyStat::query()
                    ->firstOrCreate(['user_id' => (int) $userId, 'stat_date' => $date])
                    ->incrementEach([
                        'views' => $group->count(),
                        'earn_micro' => (int) $group->sum('earn_micro'),
                    ]);
            }

            View::query()->whereKey($views->modelKeys())->update(['aggregated_at' => now()]);

            return $views->count();
        });
    }
}

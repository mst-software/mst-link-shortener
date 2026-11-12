<?php

namespace App\Console\Commands;

use App\Enums\ViewStatus;
use App\Models\FraudFlag;
use App\Models\Link;
use App\Models\View;
use Illuminate\Console\Command;

class ScanFraud extends Command
{
    /**
     * More valid views than this from one IP on one link in a day raise a flag.
     */
    private const MAX_VIEWS_PER_IP = 20;

    /**
     * A member needs at least this many views in a day before the invalid ratio is judged.
     */
    private const MIN_VIEWS_FOR_RATIO = 50;

    /**
     * Invalid ratio above which a member is flagged.
     */
    private const MAX_INVALID_RATIO = 0.6;

    protected $signature = 'fraud:scan';

    protected $description = 'Flag IPs with too many valid views on one link and members with a high share of invalid views today';

    public function handle(): int
    {
        $since = now()->startOfDay();

        $created = $this->scanIpFlooding($since) + $this->scanInvalidRatios($since);

        $this->info('Created '.$created.' fraud flags.');

        return self::SUCCESS;
    }

    private function scanIpFlooding(\DateTimeInterface $since): int
    {
        $created = 0;

        $rows = View::query()
            ->selectRaw('link_id, ip, count(*) as total')
            ->whereIn('status', [ViewStatus::Pending, ViewStatus::Released])
            ->where('created_at', '>=', $since)
            ->groupBy('link_id', 'ip')
            ->havingRaw('count(*) > ?', [self::MAX_VIEWS_PER_IP])
            ->get();

        foreach ($rows as $row) {
            $alreadyFlagged = FraudFlag::query()
                ->where('reason', 'ip_flood')
                ->where('link_id', $row->link_id)
                ->where('details->ip', $row->ip)
                ->where('created_at', '>=', $since)
                ->exists();

            if ($alreadyFlagged) {
                continue;
            }

            FraudFlag::query()->create([
                'user_id' => Link::query()->whereKey($row->link_id)->value('user_id'),
                'link_id' => $row->link_id,
                'reason' => 'ip_flood',
                'details' => ['ip' => $row->ip, 'views' => (int) $row->total, 'date' => $since->format('Y-m-d')],
            ]);
            $created++;
        }

        return $created;
    }

    private function scanInvalidRatios(\DateTimeInterface $since): int
    {
        $created = 0;

        $rows = View::query()
            ->selectRaw('user_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as invalid_total', [ViewStatus::Invalid->value])
            ->where('created_at', '>=', $since)
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [self::MIN_VIEWS_FOR_RATIO])
            ->get();

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $invalid = (int) $row->invalid_total;

            if ($invalid / $total <= self::MAX_INVALID_RATIO) {
                continue;
            }

            $alreadyFlagged = FraudFlag::query()
                ->where('reason', 'invalid_ratio')
                ->where('user_id', $row->user_id)
                ->where('created_at', '>=', $since)
                ->exists();

            if ($alreadyFlagged) {
                continue;
            }

            FraudFlag::query()->create([
                'user_id' => $row->user_id,
                'link_id' => null,
                'reason' => 'invalid_ratio',
                'details' => ['views' => $total, 'invalid' => $invalid, 'ratio' => round($invalid / $total, 2), 'date' => $since->format('Y-m-d')],
            ]);
            $created++;
        }

        return $created;
    }
}

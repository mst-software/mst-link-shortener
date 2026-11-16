<?php

namespace App\Console\Commands;

use App\Enums\ScheduleStatus;
use App\Services\ScheduleHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScheduleHealthCommand extends Command
{
    protected $signature = 'schedule:health';

    protected $description = 'Show when each scheduled command last succeeded; exits non-zero when any is late or failing';

    public function handle(ScheduleHealth $health): int
    {
        $report = $health->report();

        $this->table(
            ['Command', 'Every', 'Last success', 'Last failure', 'Status'],
            array_map(fn (array $job): array => [
                $job['command'],
                $job['interval_minutes'].' min',
                $job['last_success_at']?->toDateTimeString() ?? 'Never',
                $job['last_failure_at'] === null ? '-' : $job['last_failure_at']->toDateTimeString().' '.Str::limit((string) $job['last_failure_message'], 80),
                $job['status']->label(),
            ], $report),
        );

        $unhealthy = array_filter($report, fn (array $job): bool => $job['status'] !== ScheduleStatus::Ok);

        if ($unhealthy !== []) {
            $this->error(count($unhealthy).' scheduled job(s) late or failing. Is the scheduler running?');

            return self::FAILURE;
        }

        $this->info('All scheduled jobs are healthy.');

        return self::SUCCESS;
    }
}

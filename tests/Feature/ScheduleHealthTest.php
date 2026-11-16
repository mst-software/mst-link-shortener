<?php

use App\Enums\ScheduleStatus;
use App\Services\ScheduleHealth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-16 10:00:00');
});

function scheduledEvent(string $command): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => ScheduleHealth::commandName($event) === $command);
}

/**
 * @return array{command: string, interval_minutes: int, last_success_at: ?CarbonImmutable, last_failure_at: ?CarbonImmutable, last_failure_message: ?string, status: ScheduleStatus, is_overdue: bool}
 */
function jobHealth(string $command): array
{
    return collect(app(ScheduleHealth::class)->report())->firstWhere('command', $command);
}

/**
 * Every job except the given ones has just succeeded.
 *
 * @param  list<string>  $except
 */
function markJobsHealthy(array $except = []): void
{
    foreach (array_keys(ScheduleHealth::JOBS) as $command) {
        if (! in_array($command, $except, true)) {
            app(ScheduleHealth::class)->recordSuccess($command);
        }
    }
}

it('tracks every scheduled command with its real interval', function (): void {
    $scheduled = [];

    foreach (app(Schedule::class)->events() as $event) {
        $command = ScheduleHealth::commandName($event);
        $scheduled[$command] = (int) $event->nextRunDate(now(), 0)->diffInMinutes($event->nextRunDate(now(), 1));
    }

    $expected = ScheduleHealth::JOBS;
    ksort($scheduled);
    ksort($expected);

    expect($scheduled)->toBe($expected);
});

it('records the time of a successful scheduled run', function (): void {
    $event = scheduledEvent('views:aggregate');
    $event->exitCode = 0;

    $event->callAfterCallbacks(app());

    $job = jobHealth('views:aggregate');
    expect($job['last_success_at']->equalTo(now()))->toBeTrue()
        ->and($job['last_failure_at'])->toBeNull()
        ->and($job['status'])->toBe(ScheduleStatus::Ok);
});

it('records the time and output of a failed scheduled run', function (): void {
    app(ScheduleHealth::class)->recordSuccess('fraud:scan');
    Carbon::setTestNow(now()->addMinutes(2));
    $output = tempnam(sys_get_temp_dir(), 'schedule');
    file_put_contents($output, "  RuntimeException  \n\n  Database is gone.\n");
    $event = scheduledEvent('fraud:scan');
    $event->output = $output;
    $event->exitCode = 1;

    $event->callAfterCallbacks(app());
    unlink($output);

    $job = jobHealth('fraud:scan');
    expect($job['last_failure_at']->equalTo(now()))->toBeTrue()
        ->and($job['last_failure_message'])->toBe('RuntimeException Database is gone.')
        ->and($job['last_success_at']->equalTo(now()->subMinutes(2)))->toBeTrue()
        ->and($job['status'])->toBe(ScheduleStatus::Failed);
});

it('falls back to the exit code when a failed run printed nothing', function (): void {
    $event = scheduledEvent('sepay:sync');
    $event->output = tempnam(sys_get_temp_dir(), 'schedule');
    $event->exitCode = 2;

    $event->callAfterCallbacks(app());
    unlink($event->output);

    expect(jobHealth('sepay:sync')['last_failure_message'])->toBe('Exited with code 2.');
});

it('marks a job late when it never succeeded', function (): void {
    expect(jobHealth('views:aggregate')['status'])->toBe(ScheduleStatus::Late)
        ->and(app(ScheduleHealth::class)->isOverdue('views:aggregate'))->toBeTrue();
});

it('marks a job late only once it has not succeeded within twice its interval', function (): void {
    app(ScheduleHealth::class)->recordSuccess('views:aggregate');

    Carbon::setTestNow(now()->addMinutes(10));
    expect(jobHealth('views:aggregate')['status'])->toBe(ScheduleStatus::Ok)
        ->and(app(ScheduleHealth::class)->isOverdue('views:aggregate'))->toBeFalse();

    Carbon::setTestNow(now()->addMinute());
    expect(jobHealth('views:aggregate')['status'])->toBe(ScheduleStatus::Late)
        ->and(app(ScheduleHealth::class)->isOverdue('views:aggregate'))->toBeTrue();
});

it('clears a failure once the job succeeds again', function (): void {
    $health = app(ScheduleHealth::class);
    $health->recordFailure('earnings:release', 'Boom.');
    Carbon::setTestNow(now()->addMinute());

    $health->recordSuccess('earnings:release');

    $job = jobHealth('earnings:release');
    expect($job['status'])->toBe(ScheduleStatus::Ok)
        ->and($job['last_failure_message'])->toBe('Boom.');
});

it('exits successfully when every job is healthy', function (): void {
    markJobsHealthy();

    $this->artisan('schedule:health')
        ->expectsOutputToContain('All scheduled jobs are healthy.')
        ->assertSuccessful();
});

it('exits with a failure when a job is late', function (): void {
    markJobsHealthy();
    Carbon::setTestNow(now()->addMinutes(11));
    markJobsHealthy(except: ['views:aggregate']);

    $this->artisan('schedule:health')
        ->expectsOutputToContain('1 scheduled job(s) late or failing.')
        ->assertFailed();
});

it('exits with a failure when a job is failing', function (): void {
    markJobsHealthy();
    Carbon::setTestNow(now()->addMinute());
    app(ScheduleHealth::class)->recordFailure('views:purge', 'Disk full.');

    $this->artisan('schedule:health')
        ->expectsOutputToContain('Disk full.')
        ->assertFailed();
});

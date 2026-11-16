<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

/**
 * Heartbeats of the scheduled commands, so a scheduler that stopped running (or keeps failing) is visible.
 *
 * Each job keeps one row in the settings table ("schedule_health.<command>"). Rows are written through the
 * Setting model, not Settings::set(), so a heartbeat every minute does not flush the cached settings.
 */
class ScheduleHealth
{
    /**
     * Expected interval in minutes of every scheduled command; keep in sync with routes/console.php.
     *
     * @var array<string, int>
     */
    public const JOBS = [
        'views:aggregate' => 5,
        'earnings:release' => 1440,
        'views:purge' => 1440,
        'fraud:scan' => 60,
        'sepay:sync' => 1,
    ];

    /**
     * The job whose delay leaves the member dashboards behind.
     */
    public const STATS_JOB = 'views:aggregate';

    /**
     * A job that never ran is Late when it runs at least this often, and Waiting (e.g. right after a deploy) otherwise.
     */
    private const FREQUENT_JOB_MAX_INTERVAL_MINUTES = 60;

    private const KEY_PREFIX = 'schedule_health.';

    private const MESSAGE_LIMIT = 200;

    /**
     * Patterns of secrets that may appear in command output (API responses, URLs, SQL bindings), with their replacement.
     *
     * @var array<string, string>
     */
    private const SECRET_PATTERNS = [
        '/\bBearer\s+\S+/i' => 'Bearer [redacted]',
        '/\b(token|api[_-]?key|key|secret|password|passwd|pwd|authorization|signature)\b(\s*[=:]\s*["\']?)[^\s"\'&,;]+/i' => '$1$2[redacted]',
        '/\b[A-Fa-f0-9]{20,}\b/' => '[redacted]',
        '/(?=[A-Za-z0-9+\/_-]*\d)(?=[A-Za-z0-9+\/_-]*[A-Za-z])[A-Za-z0-9+\/_-]{32,}={0,2}/' => '[redacted]',
    ];

    /**
     * Record the outcome of a scheduled artisan command after each run.
     */
    public static function monitor(Event $event): void
    {
        $command = self::commandName($event);

        if ($command === null) {
            return;
        }

        $event->onSuccess(fn (ScheduleHealth $health) => $health->recordSuccess($command))
            ->onFailureWithOutput(function (Stringable $output, ScheduleHealth $health) use ($event, $command): void {
                $message = trim((string) $output) !== '' ? (string) $output : 'Exited with code '.$event->exitCode.'.';

                $health->recordFailure($command, $message);
            });
    }

    /**
     * The first non-empty line of a failure output, with likely secrets redacted and capped in length.
     */
    public static function summarizeFailure(string $output): string
    {
        $firstLine = collect(preg_split('/\R/', $output))
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '', '');

        $redacted = preg_replace(array_keys(self::SECRET_PATTERNS), array_values(self::SECRET_PATTERNS), $firstLine);

        return Str::limit(Str::squish((string) $redacted), self::MESSAGE_LIMIT);
    }

    /**
     * The artisan command name of a scheduled event, such as "views:aggregate", or null for closures and shell commands.
     */
    public static function commandName(Event $event): ?string
    {
        if (! is_string($event->command) || ! Str::contains($event->command, 'artisan')) {
            return null;
        }

        $name = Str::of($event->command)->after('artisan')->ltrim('\'" ')->before(' ')->value();

        return $name !== '' ? $name : null;
    }

    public function recordSuccess(string $command): void
    {
        $this->store($command, ['last_success_at' => now()->toIso8601String()]);
    }

    public function recordFailure(string $command, string $message): void
    {
        $this->store($command, [
            'last_failure_at' => now()->toIso8601String(),
            'last_failure_message' => self::summarizeFailure($message),
        ]);
    }

    /**
     * Health of every scheduled job, in schedule order.
     *
     * @return list<array{command: string, interval_minutes: int, last_success_at: ?CarbonImmutable, last_failure_at: ?CarbonImmutable, last_failure_message: ?string, status: ScheduleStatus, is_overdue: bool}>
     */
    public function report(): array
    {
        $rows = Setting::query()
            ->whereIn('key', array_map(fn (string $command): string => self::KEY_PREFIX.$command, array_keys(self::JOBS)))
            ->pluck('value', 'key');

        $report = [];

        foreach (self::JOBS as $command => $intervalMinutes) {
            $report[] = $this->describe($command, $intervalMinutes, $this->decode($rows->get(self::KEY_PREFIX.$command)));
        }

        return $report;
    }

    /**
     * Whether the job has not succeeded within twice its interval.
     */
    public function isOverdue(string $command): bool
    {
        $row = Setting::query()->where('key', self::KEY_PREFIX.$command)->value('value');

        return $this->describe($command, self::JOBS[$command], $this->decode($row))['is_overdue'];
    }

    /**
     * @param  array{last_success_at?: string, last_failure_at?: string, last_failure_message?: string}  $heartbeat
     * @return array{command: string, interval_minutes: int, last_success_at: ?CarbonImmutable, last_failure_at: ?CarbonImmutable, last_failure_message: ?string, status: ScheduleStatus, is_overdue: bool}
     */
    private function describe(string $command, int $intervalMinutes, array $heartbeat): array
    {
        $lastSuccessAt = isset($heartbeat['last_success_at']) ? CarbonImmutable::parse($heartbeat['last_success_at']) : null;
        $lastFailureAt = isset($heartbeat['last_failure_at']) ? CarbonImmutable::parse($heartbeat['last_failure_at']) : null;

        $isOverdue = $lastSuccessAt === null || $lastSuccessAt->lt(now()->subMinutes($intervalMinutes * 2));
        $failedSinceSuccess = $lastFailureAt !== null && ($lastSuccessAt === null || $lastFailureAt->gt($lastSuccessAt));

        return [
            'command' => $command,
            'interval_minutes' => $intervalMinutes,
            'last_success_at' => $lastSuccessAt,
            'last_failure_at' => $lastFailureAt,
            'last_failure_message' => $heartbeat['last_failure_message'] ?? null,
            'status' => match (true) {
                $failedSinceSuccess => ScheduleStatus::Failed,
                $lastSuccessAt === null && $intervalMinutes > self::FREQUENT_JOB_MAX_INTERVAL_MINUTES => ScheduleStatus::Waiting,
                $isOverdue => ScheduleStatus::Late,
                default => ScheduleStatus::Ok,
            },
            'is_overdue' => $isOverdue,
        ];
    }

    /**
     * @param  array<string, string>  $changes
     */
    private function store(string $command, array $changes): void
    {
        $key = self::KEY_PREFIX.$command;
        $current = $this->decode(Setting::query()->where('key', $key)->value('value'));

        Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode([...$current, ...$changes])]);
    }

    /**
     * @return array<string, string>
     */
    private function decode(?string $value): array
    {
        $decoded = $value === null ? null : json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}

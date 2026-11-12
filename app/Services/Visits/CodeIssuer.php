<?php

namespace App\Services\Visits;

use App\Models\Campaign;
use App\Models\CodeAttempt;
use App\Services\Settings;
use App\Services\Views\VisitorContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Drives the widget's two-stage wait on the server clock and hands out the code.
 *
 * Every method returns either a payload array or an error array
 * (`['error' => 'too_early'|'campaign_unavailable'|'bad_token', ...]`).
 */
class CodeIssuer
{
    public const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const CODE_LENGTH = 6;

    private const TOKEN_PURPOSE = 'widget-attempt|';

    /**
     * Sign an attempt id so the widget can hold it without being able to forge another.
     */
    public function tokenFor(CodeAttempt $attempt): string
    {
        return $attempt->id.'.'.$this->signature($attempt->id);
    }

    /**
     * Resolve an attempt from a signed token; null when the token is malformed, forged or unknown.
     */
    public function attemptFromToken(?string $token): ?CodeAttempt
    {
        if (! is_string($token) || substr_count($token, '.') !== 1) {
            return null;
        }

        [$id, $signature] = explode('.', $token);

        if (! preg_match('/\A[0-9A-Za-z]{26}\z/', $id) || ! hash_equals($this->signature($id), $signature)) {
            return null;
        }

        return CodeAttempt::query()->with('campaign')->find($id);
    }

    /**
     * Create a new attempt or continue the one the token points at.
     *
     * @param  ?CodeAttempt  $existing  an attempt already verified by the caller as belonging to this campaign
     * @return array<string, mixed>
     */
    public function start(
        Campaign $campaign,
        VisitorContext $context,
        string $pageLoadId,
        ?string $referrer,
        ?string $fingerprint,
        ?CodeAttempt $existing = null,
    ): array {
        if ($existing !== null && ! $this->isFinished($existing)) {
            return $this->payload($existing, $campaign);
        }

        if ($this->limitReached($campaign)) {
            return ['error' => 'campaign_unavailable'];
        }

        $attempt = CodeAttempt::query()->create([
            'campaign_id' => $campaign->id,
            'ip' => $context->ip,
            'ua_hash' => $context->uaHash,
            'fingerprint' => $fingerprint === null ? null : substr($fingerprint, 0, 64),
            'referrer_host' => $this->referrerHost($referrer),
            'started_at' => now(),
            'page_load_ids' => [$pageLoadId],
        ]);

        return $this->payload($attempt, $campaign);
    }

    /**
     * Move to stage 2 once stage 1 has elapsed on the server and this is a different page load.
     *
     * @return array<string, mixed>
     */
    public function progress(CodeAttempt $attempt, Campaign $campaign, string $pageLoadId): array
    {
        if ($attempt->code !== null || $attempt->progressed_at !== null) {
            return $this->summary($attempt, $campaign);
        }

        $remaining = $this->remaining($attempt->started_at->getTimestamp(), $campaign->stage1_seconds);

        if ($remaining > 0) {
            return ['error' => 'too_early', 'remaining_seconds' => $remaining];
        }

        $loadIds = $attempt->page_load_ids ?? [];

        if (in_array($pageLoadId, $loadIds, true)) {
            return ['error' => 'too_early', 'remaining_seconds' => 1];
        }

        $loadIds[] = $pageLoadId;

        CodeAttempt::query()
            ->whereKey($attempt->id)
            ->whereNull('progressed_at')
            ->update([
                'progressed_at' => now(),
                'page_load_ids' => json_encode(array_slice($loadIds, -20)),
                'updated_at' => now(),
            ]);

        return $this->summary($attempt->refresh(), $campaign);
    }

    /**
     * Issue the code once both stages have elapsed; calling again returns the same code.
     *
     * @return array<string, mixed>
     */
    public function issue(CodeAttempt $attempt, Campaign $campaign): array
    {
        if ($attempt->code !== null) {
            return ['code' => $attempt->code];
        }

        if ($attempt->progressed_at === null) {
            $remaining = $this->remaining($attempt->started_at->getTimestamp(), $campaign->stage1_seconds) + $campaign->stage2_seconds;

            return ['error' => 'too_early', 'remaining_seconds' => max(1, $remaining)];
        }

        $remaining = $this->remaining($attempt->progressed_at->getTimestamp(), $campaign->stage2_seconds);

        if ($remaining > 0) {
            return ['error' => 'too_early', 'remaining_seconds' => $remaining];
        }

        if ($this->limitReached($campaign)) {
            return ['error' => 'campaign_unavailable'];
        }

        $code = DB::transaction(function () use ($attempt, $campaign): string {
            $locked = CodeAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($locked->code !== null) {
                return $locked->code;
            }

            $locked->forceFill([
                'code' => $this->generateCode($campaign),
                'issued_at' => now(),
                'expires_at' => now()->addSeconds(Settings::codeSessionSeconds()),
            ])->save();

            return (string) $locked->code;
        });

        return ['code' => $code];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CodeAttempt $attempt, Campaign $campaign): array
    {
        $payload = $this->summary($attempt, $campaign) + [
            'attempt_token' => $this->tokenFor($attempt),
            'stage1_seconds' => $campaign->stage1_seconds,
            'stage2_seconds' => $campaign->stage2_seconds,
        ];

        if ($attempt->code !== null) {
            $payload['code'] = $attempt->code;
        }

        return $payload;
    }

    /**
     * @return array{stage: int, remaining_seconds: int}
     */
    private function summary(CodeAttempt $attempt, Campaign $campaign): array
    {
        if ($attempt->code !== null) {
            return ['stage' => 3, 'remaining_seconds' => 0];
        }

        if ($attempt->progressed_at !== null) {
            return [
                'stage' => 2,
                'remaining_seconds' => $this->remaining($attempt->progressed_at->getTimestamp(), $campaign->stage2_seconds),
            ];
        }

        return [
            'stage' => 1,
            'remaining_seconds' => $this->remaining($attempt->started_at->getTimestamp(), $campaign->stage1_seconds),
        ];
    }

    /**
     * Seconds left of a stage, measured only against the server clock.
     */
    private function remaining(int $since, int $stageSeconds): int
    {
        return max(0, $stageSeconds - (now()->getTimestamp() - $since));
    }

    /**
     * An attempt whose code was used or has expired starts over instead of reviving the old code.
     */
    private function isFinished(CodeAttempt $attempt): bool
    {
        return $attempt->used_at !== null
            || ($attempt->code !== null && $attempt->expires_at !== null && $attempt->expires_at->isPast());
    }

    private function limitReached(Campaign $campaign): bool
    {
        return $campaign->daily_limit > 0 && $campaign->issuedToday() >= $campaign->daily_limit;
    }

    private function generateCode(Campaign $campaign): string
    {
        $last = strlen(self::ALPHABET) - 1;

        for ($try = 0; $try < 20; $try++) {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, $last)];
            }

            if (! $campaign->attempts()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique code for the campaign.');
    }

    private function referrerHost(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? substr(strtolower($host), 0, 255) : null;
    }

    private function signature(string $id): string
    {
        return hash_hmac('sha256', self::TOKEN_PURPOSE.$id, (string) config('app.key'));
    }
}

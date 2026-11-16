<?php

namespace App\Services;

use App\Enums\FootprintSignal;
use App\Enums\LoginEvent;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Finds other accounts that share sign-up and sign-in footprints (user_logins) with a user, for admin review.
 * It never bans or blocks anyone; the result is only shown to admins (and used to raise fraud flags).
 *
 * Footprints compared: the sign-up IP, every sign-in IP and every user agent hash of the last LOOKBACK_DAYS
 * (sign-up rows count whatever their age, until logins:purge removes them). An IP address or user agent hash
 * used by more than "multi_account_shared_ip_limit" distinct accounts is a shared network (office, school,
 * mobile carrier) or a very common browser, so it is ignored to avoid false positives.
 * See FootprintSignal for the scoring weights.
 */
class MultiAccountDetector
{
    public const LOOKBACK_DAYS = 30;

    public const MAX_RESULTS = 20;

    /**
     * Accounts that look related to the user, highest score first.
     *
     * @return Collection<int, RelatedAccount>
     */
    public function relatedAccounts(User $user, int $limit = self::MAX_RESULTS): Collection
    {
        return $this->detect($user)->sortByDesc(fn (RelatedAccount $related): int => $related->score())->take($limit)->values();
    }

    /**
     * How the two accounts are related, or null when they share no footprint.
     */
    public function compare(User $user, User $other): ?RelatedAccount
    {
        return $this->detect($user, $other)->first();
    }

    /**
     * @return Collection<int, RelatedAccount>
     */
    private function detect(User $user, ?User $only = null): Collection
    {
        $own = $this->footprints($user->logins()->getQuery())->get(['event', 'ip', 'ua_hash']);

        if ($own->isEmpty()) {
            return collect();
        }

        $signupIp = $own->firstWhere('event', LoginEvent::Signup)?->ip;
        $ips = $this->withoutSharedValues('ip', $own->pluck('ip')->unique()->values());
        $uaHashes = $this->withoutSharedValues('ua_hash', $own->pluck('ua_hash')->unique()->values());

        if ($ips->isEmpty() && $uaHashes->isEmpty()) {
            return collect();
        }

        $matches = $this->footprints(UserLogin::query())
            ->where('user_id', '!=', $user->getKey())
            ->when($only !== null, fn (Builder $query) => $query->where('user_id', $only->getKey()))
            ->where(fn (Builder $query) => $query->whereIn('ip', $ips)->orWhereIn('ua_hash', $uaHashes))
            ->get(['user_id', 'event', 'ip', 'ua_hash'])
            ->groupBy('user_id');

        if ($matches->isEmpty()) {
            return collect();
        }

        $users = User::query()->whereKey($matches->keys())->get(['id', 'username', 'status', 'role', 'referred_by', 'created_at'])->keyBy('id');
        $lastLogins = $this->lastLogins($matches->keys()->all());

        return $matches->map(function (Collection $rows, int $otherId) use ($user, $users, $lastLogins, $signupIp, $ips, $uaHashes): ?RelatedAccount {
            $other = $users->get($otherId);

            if ($other === null) {
                return null;
            }

            $sharedIps = $rows->pluck('ip')->unique()->intersect($ips)->values();
            $isSameSignupIp = $signupIp !== null
                && $ips->contains($signupIp)
                && $rows->contains(fn (UserLogin $row): bool => $row->event === LoginEvent::Signup && $row->ip === $signupIp);

            $signals = [];

            if ($isSameSignupIp) {
                $signals[] = FootprintSignal::SameSignupIp;
            }

            if ($rows->pluck('ua_hash')->intersect($uaHashes)->isNotEmpty()) {
                $signals[] = FootprintSignal::SameDevice;
            }

            if ($sharedIps->reject(fn (string $ip): bool => $isSameSignupIp && $ip === $signupIp)->isNotEmpty()) {
                $signals[] = FootprintSignal::SharedLoginIp;
            }

            if ($signals === []) {
                return null;
            }

            if ($other->referred_by === $user->getKey() || $user->referred_by === $other->getKey()) {
                $signals[] = FootprintSignal::ReferralRelation;
            }

            $lastLogin = $lastLogins->get($otherId);

            return new RelatedAccount($other, $signals, $sharedIps->all(), $lastLogin?->ip, $lastLogin?->created_at);
        })->filter()->values();
    }

    /**
     * Footprints inside the lookback window, plus sign-up rows of any age.
     *
     * @param  Builder<UserLogin>  $query
     * @return Builder<UserLogin>
     */
    private function footprints(Builder $query): Builder
    {
        return $query->where(fn (Builder $inner) => $inner
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->orWhere('event', LoginEvent::Signup));
    }

    /**
     * Drop the values used by more distinct accounts than the shared network limit.
     *
     * @param  Collection<int, string>  $values
     * @return Collection<int, string>
     */
    private function withoutSharedValues(string $column, Collection $values): Collection
    {
        if ($values->isEmpty()) {
            return $values;
        }

        $limit = max(2, Settings::int('multi_account_shared_ip_limit'));

        $accounts = UserLogin::query()
            ->whereIn($column, $values)
            ->toBase()
            ->selectRaw($column.' as value, count(distinct user_id) as accounts')
            ->groupBy($column)
            ->pluck('accounts', 'value');

        return $values->filter(fn (string $value): bool => (int) ($accounts[$value] ?? 0) <= $limit)->values();
    }

    /**
     * The most recent footprint of each user.
     *
     * @param  list<int>  $userIds
     * @return EloquentCollection<int, UserLogin>
     */
    private function lastLogins(array $userIds): EloquentCollection
    {
        $latestIds = UserLogin::query()->whereIn('user_id', $userIds)->toBase()->selectRaw('max(id) as id')->groupBy('user_id')->pluck('id');

        return UserLogin::query()->whereKey($latestIds->all())->get(['id', 'user_id', 'ip', 'created_at'])->keyBy('user_id');
    }
}

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
 * (sign-up rows count at any age within the retention period, until logins:purge removes them).
 *
 * Shared networks: an IP address or user agent hash used by more than "multi_account_shared_ip_limit"
 * distinct accounts is probably an office, school, mobile carrier or a very common browser. A signal that only
 * comes from such a value is kept but weak (RelatedAccount::SHARED_WEIGHT_PERCENT of its weight), so a farm of
 * many accounts on one address still shows up without flooding every user page. The trade-off: shared values
 * add noise at a low score, and the listing fetches at most MAX_SHARED_MATCHES footprints through them.
 * Comparing one specific pair (compare()) always looks at every value. See FootprintSignal for the weights.
 */
class MultiAccountDetector
{
    public const LOOKBACK_DAYS = 30;

    public const MAX_RESULTS = 20;

    /**
     * Most footprints fetched through shared values when listing related accounts.
     */
    public const MAX_SHARED_MATCHES = 500;

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
        $ips = $own->pluck('ip')->unique()->values();
        $uaHashes = $own->pluck('ua_hash')->unique()->values();
        $sharedIps = $this->sharedValues('ip', $ips);
        $sharedUaHashes = $this->sharedValues('ua_hash', $uaHashes);

        $matches = $this->matches($user, $only, $ips, $uaHashes, $sharedIps, $sharedUaHashes)->groupBy('user_id');

        if ($matches->isEmpty()) {
            return collect();
        }

        $users = User::query()->whereKey($matches->keys())->get(['id', 'username', 'status', 'role', 'referred_by', 'created_at'])->keyBy('id');
        $lastLogins = $this->lastLogins($matches->keys()->all());

        return $matches->map(function (Collection $rows, int $otherId) use ($user, $users, $lastLogins, $signupIp, $ips, $uaHashes, $sharedIps, $sharedUaHashes): ?RelatedAccount {
            $other = $users->get($otherId);

            if ($other === null) {
                return null;
            }

            $matchedIps = $rows->pluck('ip')->unique()->intersect($ips)->values();
            $matchedUaHashes = $rows->pluck('ua_hash')->unique()->intersect($uaHashes)->values();
            $isSameSignupIp = $signupIp !== null
                && $rows->contains(fn (UserLogin $row): bool => $row->event === LoginEvent::Signup && $row->ip === $signupIp);
            $otherIps = $matchedIps->reject(fn (string $ip): bool => $isSameSignupIp && $ip === $signupIp);

            $signals = [];
            $weakSignals = [];

            if ($isSameSignupIp) {
                $signals[] = FootprintSignal::SameSignupIp;

                if ($sharedIps->contains($signupIp)) {
                    $weakSignals[] = FootprintSignal::SameSignupIp;
                }
            }

            if ($matchedUaHashes->isNotEmpty()) {
                $signals[] = FootprintSignal::SameDevice;

                if ($matchedUaHashes->diff($sharedUaHashes)->isEmpty()) {
                    $weakSignals[] = FootprintSignal::SameDevice;
                }
            }

            if ($otherIps->isNotEmpty()) {
                $signals[] = FootprintSignal::SharedLoginIp;

                if ($otherIps->diff($sharedIps)->isEmpty()) {
                    $weakSignals[] = FootprintSignal::SharedLoginIp;
                }
            }

            if ($signals === []) {
                return null;
            }

            if ($other->referred_by === $user->getKey() || $user->referred_by === $other->getKey()) {
                $signals[] = FootprintSignal::ReferralRelation;
            }

            $lastLogin = $lastLogins->get($otherId);

            return new RelatedAccount($other, $signals, $matchedIps->all(), $lastLogin?->ip, $lastLogin?->created_at, $weakSignals);
        })->filter()->values();
    }

    /**
     * Other accounts' footprints that share an IP address or user agent hash with the user. When listing, values
     * used by many accounts are looked up separately and capped, so one busy address cannot load thousands of rows.
     *
     * @param  Collection<int, string>  $ips
     * @param  Collection<int, string>  $uaHashes
     * @param  Collection<int, string>  $sharedIps
     * @param  Collection<int, string>  $sharedUaHashes
     * @return EloquentCollection<int, UserLogin>
     */
    private function matches(User $user, ?User $only, Collection $ips, Collection $uaHashes, Collection $sharedIps, Collection $sharedUaHashes): EloquentCollection
    {
        $query = fn (Collection $matchIps, Collection $matchUaHashes): Builder => $this->footprints(UserLogin::query())
            ->where('user_id', '!=', $user->getKey())
            ->when($only !== null, fn (Builder $inner) => $inner->where('user_id', $only->getKey()))
            ->where(fn (Builder $inner) => $inner->whereIn('ip', $matchIps)->orWhereIn('ua_hash', $matchUaHashes));

        if ($only !== null) {
            return $query($ips, $uaHashes)->get(['id', 'user_id', 'event', 'ip', 'ua_hash']);
        }

        $rows = $query($ips->diff($sharedIps), $uaHashes->diff($sharedUaHashes))->get(['id', 'user_id', 'event', 'ip', 'ua_hash']);

        if ($sharedIps->isNotEmpty() || $sharedUaHashes->isNotEmpty()) {
            $rows = $rows->merge(
                $query($sharedIps, $sharedUaHashes)->latest('id')->limit(self::MAX_SHARED_MATCHES)->get(['id', 'user_id', 'event', 'ip', 'ua_hash']),
            );
        }

        return $rows;
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
     * The values used by more distinct accounts than the shared network limit (counted over the same footprints).
     *
     * @param  Collection<int, string>  $values
     * @return Collection<int, string>
     */
    private function sharedValues(string $column, Collection $values): Collection
    {
        if ($values->isEmpty()) {
            return $values;
        }

        $limit = max(2, Settings::int('multi_account_shared_ip_limit'));

        $accounts = $this->footprints(UserLogin::query())
            ->whereIn($column, $values)
            ->toBase()
            ->selectRaw($column.' as value, count(distinct user_id) as accounts')
            ->groupBy($column)
            ->pluck('accounts', 'value');

        return $values->filter(fn (string $value): bool => (int) ($accounts[$value] ?? 0) > $limit)->values();
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

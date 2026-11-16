<?php

namespace App\Models;

use App\Enums\LoginEvent;
use Database\Factories\UserLoginFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sign-up or sign-in footprint: the IP address, a hash of the browser user agent, the trimmed user
 * agent and the country. Used to find accounts run by the same person; purged by logins:purge.
 */
class UserLogin extends Model
{
    /** @use HasFactory<UserLoginFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Longest user agent kept on a row.
     */
    public const USER_AGENT_LENGTH = 255;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'ip',
        'ua_hash',
        'user_agent',
        'country',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => LoginEvent::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Footprints recorded at or after the given time.
     *
     * @param  Builder<UserLogin>  $query
     * @return Builder<UserLogin>
     */
    public function scopeSince(Builder $query, DateTimeInterface $since): Builder
    {
        return $query->where('created_at', '>=', $since);
    }
}

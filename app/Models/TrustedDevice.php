<?php

namespace App\Models;

use Database\Factories\TrustedDeviceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser where the user chose "remember this device" after passing two-factor.
 * Only the SHA-256 hash of the cookie token is stored.
 */
class TrustedDevice extends Model
{
    /** @use HasFactory<TrustedDeviceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
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
     * Devices whose trust has not expired yet.
     *
     * @param  Builder<TrustedDevice>  $query
     * @return Builder<TrustedDevice>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}

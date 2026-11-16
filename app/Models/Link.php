<?php

namespace App\Models;

use App\Enums\LinkStatus;
use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'alias',
        'url',
        'level',
        'status',
        'password',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'level' => 1,
        'status' => 'active',
        'views_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'views_count' => 'integer',
            'status' => LinkStatus::class,
            'password' => 'hashed',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Whether visitors must enter a password before the visit flow starts.
     */
    public function isPasswordProtected(): bool
    {
        return $this->password !== null;
    }

    /**
     * Whether the expiry date has passed; a link without one never expires.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Keep only links without an expiry date or with one still in the future.
     *
     * @param  Builder<Link>  $query
     * @return Builder<Link>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<View, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }
}

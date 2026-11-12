<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Services\Settings;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'keyword',
        'domain',
        'display_domain',
        'widget_key',
        'is_active',
        'weight',
        'daily_limit',
        'price_micro',
        'review_status',
        'review_note',
        'starts_at',
        'ends_at',
        'stage1_seconds',
        'stage2_seconds',
        'image_google',
        'image_button',
        'image_position',
        'notes',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'review_status' => 'approved',
        'weight' => 1,
        'daily_limit' => 0,
        'stage1_seconds' => 60,
        'stage2_seconds' => 15,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'weight' => 'integer',
            'daily_limit' => 'integer',
            'price_micro' => 'integer',
            'review_status' => ReviewStatus::class,
            'stage1_seconds' => 'integer',
            'stage2_seconds' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * A random widget key that no campaign uses yet.
     */
    public static function generateWidgetKey(): string
    {
        do {
            $key = Str::lower(Str::random(24));
        } while (self::query()->where('widget_key', $key)->exists());

        return $key;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CodeAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CodeAttempt::class);
    }

    /**
     * Campaigns that are switched on and inside their optional date window. A campaign with an
     * advertiser behind it must also be approved, belong to an active advertiser and have a wallet
     * that can pay for at least one more view.
     *
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $owned) => $owned->whereNull('user_id')->orWhere(fn (Builder $advertiser) => $advertiser
                ->where('review_status', ReviewStatus::Approved)
                ->whereHas('owner', fn (Builder $owner) => $owner
                    ->where('status', UserStatus::Active)
                    ->whereRaw('users.balance_micro >= COALESCE(campaigns.price_micro, ?)', [Settings::int('advertiser_default_price_micro')]))))
            ->where(fn (Builder $window) => $window->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $window) => $window->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /**
     * Whether an admin decision or the advertiser's account state forbids this campaign from serving
     * anyone, even a visitor who already holds a code. A wallet that ran out does not count: the
     * visitor may finish the visit that was already started.
     */
    public function isBlocked(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $this->loadMissing('owner');

        return $this->review_status !== ReviewStatus::Approved
            || $this->owner === null
            || $this->owner->status !== UserStatus::Active;
    }

    /**
     * Number of codes issued so far today (UTC day).
     */
    public function issuedToday(): int
    {
        return $this->attempts()
            ->whereBetween('issued_at', [now('UTC')->startOfDay(), now('UTC')->endOfDay()])
            ->count();
    }
}

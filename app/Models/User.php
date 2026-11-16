<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'fullname',
        'phone_number',
        'address',
        'district',
        'province',
        'country',
        'traffic_source',
        'default_level',
        'payout_method',
        'payout_details',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Member->value,
        'status' => UserStatus::Active->value,
        'balance_micro' => 0,
        'processing_micro' => 0,
        'default_level' => 1,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'api_token',
        'api_token_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_last_used_step',
    ];

    /**
     * Generate the API token and referral code when they are not provided, and keep the
     * lookup hash of the API token in step with the token itself.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->api_token ??= static::generateApiToken();
            $user->api_token_hash = static::hashApiToken($user->api_token);
            $user->referral_code ??= static::generateUnique('referral_code', 7);
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('api_token')) {
                $user->api_token_hash = static::hashApiToken($user->api_token);
            }
        });
    }

    /**
     * Find the user an API token belongs to. Only the SHA-256 hash of the token is looked up.
     */
    public static function findByApiToken(string $token): ?self
    {
        $hash = static::hashApiToken($token);
        $user = static::query()->where('api_token_hash', $hash)->first();

        return $user !== null && hash_equals((string) $user->api_token_hash, $hash) ? $user : null;
    }

    /**
     * The hash stored in api_token_hash for a plain API token.
     */
    public static function hashApiToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Replace the API token with a new one. The old token stops working at once. The row is written
     * directly so a stored token that can no longer be decrypted (after an APP_KEY change) is never read.
     */
    public function regenerateApiToken(): string
    {
        $token = static::generateApiToken();

        static::query()->whereKey($this->getKey())->update([
            'api_token' => Crypt::encryptString($token),
            'api_token_hash' => static::hashApiToken($token),
        ]);

        $this->refresh();

        return $token;
    }

    /**
     * Generate a random API token whose hash is not used yet.
     */
    protected static function generateApiToken(): string
    {
        do {
            $token = Str::random(24);
        } while (static::query()->where('api_token_hash', static::hashApiToken($token))->exists());

        return $token;
    }

    /**
     * Generate a random string that is not yet used in the given column.
     */
    protected static function generateUnique(string $column, int $length): string
    {
        do {
            $value = Str::random($length);
        } while (static::query()->where($column, $value)->exists());

        return $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'api_token' => 'encrypted',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'payout_method' => PayoutMethod::class,
            'payout_details' => 'array',
            'balance_micro' => 'integer',
            'processing_micro' => 'integer',
            'terms_accepted_at' => 'datetime',
            'default_level' => 'integer',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Browsers that may skip the two-factor step until they expire.
     *
     * @return HasMany<TrustedDevice, $this>
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * @return HasMany<TopUp, $this>
     */
    public function topUps(): HasMany
    {
        return $this->hasMany(TopUp::class);
    }

    /**
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function isAdvertiser(): bool
    {
        return $this->role === UserRole::Advertiser;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Whether the user has confirmed an authenticator app and must enter its codes.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /**
     * The URL of the dashboard that belongs to this user's role.
     */
    public function dashboardUrl(): string
    {
        return match (true) {
            $this->isAdmin() => route('admin.dashboard'),
            $this->isAdvertiser() => route('advertiser.dashboard'),
            default => route('member.dashboard'),
        };
    }

    /**
     * The user who invited this user.
     *
     * @return BelongsTo<User, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    /**
     * The users invited by this user.
     *
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    /**
     * Sign-up and sign-in footprints (IP address and browser) of this user.
     *
     * @return HasMany<UserLogin, $this>
     */
    public function logins(): HasMany
    {
        return $this->hasMany(UserLogin::class);
    }
}

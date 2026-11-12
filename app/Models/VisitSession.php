<?php

namespace App\Models;

use App\Enums\VisitMode;
use App\Enums\VisitStatus;
use Database\Factories\VisitSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitSession extends Model
{
    /** @use HasFactory<VisitSessionFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'ip',
        'ip_changed',
        'ua_hash',
        'fingerprint',
        'country',
        'level',
        'mode',
        'campaign_id',
        'code_failures',
        'current_step',
        'step_issued_at',
        'token_hash',
        'status',
        'expires_at',
        'finished_at',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_step' => 1,
        'ip_changed' => false,
        'country' => 'XX',
        'status' => 'in_progress',
        'mode' => 'wait',
        'code_failures' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ip_changed' => 'boolean',
            'level' => 'integer',
            'current_step' => 'integer',
            'mode' => VisitMode::class,
            'code_failures' => 'integer',
            'status' => VisitStatus::class,
            'step_issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * @return HasOne<View, $this>
     */
    public function view(): HasOne
    {
        return $this->hasOne(View::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<CodeAttempt, $this>
     */
    public function codeAttempts(): HasMany
    {
        return $this->hasMany(CodeAttempt::class);
    }
}

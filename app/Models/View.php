<?php

namespace App\Models;

use App\Enums\InvalidReason;
use App\Enums\ViewStatus;
use Database\Factories\ViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class View extends Model
{
    /** @use HasFactory<ViewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'user_id',
        'visit_session_id',
        'campaign_id',
        'ip',
        'country',
        'level',
        'status',
        'invalid_reason',
        'earn_micro',
        'advertiser_cost_micro',
        'matures_at',
        'aggregated_at',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'country' => 'XX',
        'status' => 'pending',
        'earn_micro' => 0,
        'advertiser_cost_micro' => 0,
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
            'earn_micro' => 'integer',
            'advertiser_cost_micro' => 'integer',
            'status' => ViewStatus::class,
            'invalid_reason' => InvalidReason::class,
            'matures_at' => 'datetime',
            'aggregated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<VisitSession, $this>
     */
    public function visitSession(): BelongsTo
    {
        return $this->belongsTo(VisitSession::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\CodeAttemptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeAttempt extends Model
{
    /** @use HasFactory<CodeAttemptFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'ip',
        'ua_hash',
        'fingerprint',
        'referrer_host',
        'started_at',
        'progressed_at',
        'page_load_ids',
        'code',
        'issued_at',
        'expires_at',
        'used_at',
        'visit_session_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_load_ids' => 'array',
            'started_at' => 'datetime',
            'progressed_at' => 'datetime',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
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

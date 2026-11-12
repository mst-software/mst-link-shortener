<?php

namespace App\Models;

use App\Enums\TopUpProvider;
use App\Enums\TopUpStatus;
use App\Services\Settings;
use Database\Factories\TopUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopUp extends Model
{
    /** @use HasFactory<TopUpFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'reference',
        'status',
        'amount_micro',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'expires_at',
        'completed_at',
        'raw',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => TopUpProvider::class,
            'status' => TopUpStatus::class,
            'amount_micro' => 'integer',
            'original_amount' => 'integer',
            'exchange_rate' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'raw' => 'array',
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
     * URL of the SePay QR image that pre-fills the transfer for this top up.
     */
    public function sepayQrUrl(): string
    {
        return 'https://qr.sepay.vn/img?'.http_build_query([
            'acc' => (string) Settings::get('sepay_account_number', ''),
            'bank' => (string) Settings::get('sepay_bank_name', ''),
            'amount' => $this->original_amount,
            'des' => $this->reference,
        ]);
    }
}

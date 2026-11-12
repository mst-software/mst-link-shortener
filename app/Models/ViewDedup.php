<?php

namespace App\Models;

use Database\Factories\ViewDedupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViewDedup extends Model
{
    /** @use HasFactory<ViewDedupFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'view_dedup';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key_hash',
        'expires_at',
    ];

    /**
     * Dedup key of a paid view: one per link, IP address and browser fingerprint.
     */
    public static function keyFor(int $linkId, string $ip, string $fingerprint): string
    {
        return hash('sha256', $linkId.'|'.$ip.'|'.$fingerprint);
    }

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
}

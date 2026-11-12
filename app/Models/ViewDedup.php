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

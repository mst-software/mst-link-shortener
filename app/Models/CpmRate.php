<?php

namespace App\Models;

use Database\Factories\CpmRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CpmRate extends Model
{
    /** @use HasFactory<CpmRateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'country',
        'level',
        'rate_per_1000_micro',
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
            'rate_per_1000_micro' => 'integer',
        ];
    }
}

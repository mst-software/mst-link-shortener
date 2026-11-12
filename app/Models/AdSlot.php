<?php

namespace App\Models;

use App\Enums\AdDevice;
use App\Enums\AdPlacement;
use Database\Factories\AdSlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    /** @use HasFactory<AdSlotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'placement',
        'device',
        'network',
        'code',
        'is_active',
        'sort_order',
    ];

    /**
     * Default attribute values, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'device' => 'all',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => AdPlacement::class,
            'device' => AdDevice::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

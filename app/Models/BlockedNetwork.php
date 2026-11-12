<?php

namespace App\Models;

use Database\Factories\BlockedNetworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedNetwork extends Model
{
    /** @use HasFactory<BlockedNetworkFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cidr',
        'label',
    ];
}

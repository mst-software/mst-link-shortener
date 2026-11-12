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

    /**
     * Whether the text is an IPv4 or IPv6 address with an optional valid prefix length, for example "10.0.0.0/8".
     */
    public static function isValidCidr(string $cidr): bool
    {
        [$address, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $maxBits = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix <= $maxBits;
    }
}

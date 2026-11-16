<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Limit a string by its size in bytes. Bcrypt only reads the first 72 bytes of a password,
 * and a multibyte password reaches that before the usual character limit.
 */
class MaxBytes implements ValidationRule
{
    public function __construct(private readonly int $bytes) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->bytes) {
            $fail("The :attribute must not be longer than {$this->bytes} bytes.");
        }
    }
}

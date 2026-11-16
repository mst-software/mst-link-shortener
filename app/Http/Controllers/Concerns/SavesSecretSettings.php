<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Settings;

/**
 * Saves write-only secret settings: a blank field keeps the stored value, so secrets never have to be
 * echoed back into a form, and a ticked "clear_{key}" box removes it.
 */
trait SavesSecretSettings
{
    /**
     * @param  array<string, mixed>  $data  Validated request data.
     * @param  list<string>  $keys
     */
    protected function saveSecretSettings(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                Settings::set($key, $value);
            } elseif (filter_var($data['clear_'.$key] ?? false, FILTER_VALIDATE_BOOL)) {
                Settings::forget($key);
            }
        }
    }
}

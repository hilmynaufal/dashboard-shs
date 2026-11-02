<?php

namespace App\Support;

final class FormInput
{
    /** Invalid array/object input must not break the validation recovery page. */
    public static function oldText(string $key, mixed $default = ''): string
    {
        $value = old($key, $default);

        return is_scalar($value) ? (string)$value : '';
    }
}

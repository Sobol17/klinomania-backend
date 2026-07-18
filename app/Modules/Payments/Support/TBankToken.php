<?php

namespace App\Modules\Payments\Support;

class TBankToken
{
    public static function make(array $payload, string $password): string
    {
        $values = [];
        foreach ($payload as $key => $value) {
            if ($key === 'Token' || is_array($value) || is_object($value)) {
                continue;
            }
            $values[$key] = self::stringValue($value);
        }
        $values['Password'] = $password;
        ksort($values, SORT_STRING);

        return hash('sha256', implode('', $values));
    }

    private static function stringValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }
}

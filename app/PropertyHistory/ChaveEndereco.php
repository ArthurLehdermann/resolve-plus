<?php

namespace App\PropertyHistory;

use Illuminate\Support\Str;

final class ChaveEndereco
{
    public static function from(string $cep, string $numero, ?string $complemento = null): string
    {
        return implode('|', [
            self::normalize($cep),
            self::normalize($numero),
            self::normalize((string) $complemento),
        ]);
    }

    public static function normalize(string $value): string
    {
        $ascii = Str::upper(Str::ascii(trim($value)));

        return (string) preg_replace('/[^A-Z0-9]/', '', $ascii);
    }
}

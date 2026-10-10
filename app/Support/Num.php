<?php

namespace App\Support;

/**
 * Normalisasi angka agar output JSON sama dengan kontrak frontend
 * (bilangan bulat dikirim sebagai int, pecahan sebagai float).
 */
final class Num
{
    public static function quantity(int|float|string|null $value): int|float
    {
        $number = (float) ($value ?? 0);

        return fmod($number, 1.0) === 0.0 ? (int) $number : $number;
    }
}

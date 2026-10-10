<?php

namespace App\Services;

use App\Models\TaxRate;

/**
 * Sumber tunggal tarif pajak (PRD §7.1): frontend tidak boleh hardcode.
 */
final class TaxService
{
    public function defaultRate(): float
    {
        $rate = TaxRate::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->value('rate');

        return $rate !== null ? (float) $rate : (float) config('dulank.tax_rate_default', 11);
    }

    public function calculate(int $base, ?float $rate = null): int
    {
        $rate ??= $this->defaultRate();

        return (int) round($base * $rate / 100);
    }
}

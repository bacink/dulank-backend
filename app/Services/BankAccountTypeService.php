<?php

namespace App\Services;

use App\Models\BankAccountType;
use Illuminate\Database\Eloquent\Collection;

/**
 * Master tipe rekening bank/kas (PRD §4.5).
 */
final class BankAccountTypeService
{
    /**
     * @return Collection<int, BankAccountType>
     */
    public function list(): Collection
    {
        return BankAccountType::query()
            ->withCount('accounts')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): BankAccountType
    {
        return BankAccountType::query()->create([
            'name' => $payload['name'],
            'status' => $payload['status'] ?? 'Active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(BankAccountType $type, array $payload): BankAccountType
    {
        $type->forceFill([
            'name' => $payload['name'],
            'status' => $payload['status'] ?? $type->status,
        ])->save();

        return $type->refresh();
    }

    public function delete(BankAccountType $type): void
    {
        $type->delete();
    }
}

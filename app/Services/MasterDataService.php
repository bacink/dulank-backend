<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class MasterDataService
{
    public static function unique(string $table, string $column, mixed $value, string $field, mixed $ignore = null, array $scope = []): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return;
        }
        $query = DB::table($table)->whereRaw('LOWER(TRIM('.DB::connection()->getQueryGrammar()->wrap($column).')) = ?', [mb_strtolower(trim((string) $value))]);
        if ($ignore !== null) {
            $query->where('id', '!=', $ignore);
        }
        foreach ($scope as $key => $item) {
            $query->where($key, $item);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([$field => ['Nilai sudah digunakan. Pilih record yang ada atau gunakan nilai berbeda.']]);
        }
    }

    public static function resolve(string $class, mixed $id, ?string $label, string $field, array $scope = [], string $labelColumn = 'name'): ?int
    {
        $query = $class::query()->where($scope);
        if ($id !== null && $id !== '') {
            $record = $query->find($id);
        } elseif ($label !== null && trim($label) !== '') {
            $matches = $query->whereRaw('LOWER(TRIM('.DB::connection()->getQueryGrammar()->wrap($labelColumn).')) = ?', [mb_strtolower(trim($label))])->limit(2)->get();
            $record = $matches->count() === 1 ? $matches->first() : null;
        } else {
            return null;
        }
        if ($record === null) {
            throw ValidationException::withMessages([$field => ['Referensi tidak ditemukan atau ambigu; pilih dari master data.']]);
        }

        return (int) $record->id;
    }

    public static function assertDeletable(Model $record): void
    {
        $schema = Schema::getFacadeRoot();
        foreach ($schema->getTableListing($schema->getCurrentSchemaName(), false) as $table) {
            foreach ($schema->getForeignKeys($table) as $fk) {
                if ($fk['foreign_table'] === $record->getTable() && count($fk['columns']) === 1
                    && DB::table($table)->where($fk['columns'][0], $record->getAttribute($fk['foreign_columns'][0]))->exists()) {
                    throw ApiException::conflict('Data sudah digunakan. Nonaktifkan data tersebut agar riwayat tetap terjaga.');
                }
            }
        }
        $textReferences = match ($record->getTable()) {
            'units' => [['purchase_items', 'unit'], ['order_items', 'unit'], ['quotation_items', 'unit']],
            'variants' => [['product_variants', 'variation']],
            default => [],
        };
        foreach ($textReferences as [$table, $column]) {
            if (DB::table($table)->where($column, $record->getAttribute('name'))->exists()) {
                throw ApiException::conflict('Master masih digunakan oleh data terkait. Nonaktifkan data tersebut.');
            }
        }
        if (Address::query()->where('addressable_type', $record->getMorphClass())->where('addressable_id', $record->getKey())->exists()) {
            throw ApiException::conflict('Data masih memiliki alamat terkait. Nonaktifkan atau kelola alamat terlebih dahulu.');
        }
    }
}

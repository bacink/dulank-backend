<?php

namespace App\Console\Commands;

use App\Services\Import\AdminDataImporter;
use App\Services\Import\StoreDataImporter;
use Illuminate\Console\Command;

class ImportDulankData extends Command
{
    protected $signature = 'dulank:import
        {source=store : Sumber data (store|admin)}
        {--path= : Override lokasi direktori sumber data}
        {--dry-run : Jalankan tanpa menyimpan (transaksi di-rollback)}
        {--password= : Password awal untuk user store hasil impor}';

    protected $description = 'Impor data mock frontend ke database canonik (idempoten)';

    public function handle(): int
    {
        $source = (string) $this->argument('source');

        if (! in_array($source, ['store', 'admin'], true)) {
            $this->error('Sumber tidak dikenal. Gunakan "store" atau "admin".');

            return self::FAILURE;
        }

        $basePath = (string) ($this->option('path') ?? config('dulank.import.'.$source.'_path'));

        if ($basePath === '') {
            $this->error('Lokasi sumber data tidak diketahui. Set DULANK_'.strtoupper($source).'_PATH di .env atau gunakan --path.');

            return self::FAILURE;
        }

        if (! is_dir($basePath)) {
            $this->error("Direktori tidak ditemukan: {$basePath}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $password = (string) ($this->option('password') ?? env('DULANK_IMPORT_PASSWORD', 'password'));

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Impor data {$source} dari {$basePath}");

        $importer = $source === 'store'
            ? new StoreDataImporter($basePath, $password)
            : new AdminDataImporter($basePath, $password);

        $counts = $importer->import($dryRun, function (string $message): void {
            $this->line('  - '.$message);
        });

        $this->newLine();
        $this->table(
            ['Entitas', 'Jumlah'],
            collect($counts)->map(fn (int $count, string $entity) => [$entity, $count])->values()->all(),
        );

        $this->info($dryRun ? 'Dry run selesai (tidak ada perubahan tersimpan).' : 'Impor selesai.');

        return self::SUCCESS;
    }
}

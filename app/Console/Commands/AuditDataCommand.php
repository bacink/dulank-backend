<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditDataCommand extends Command
{
    protected $signature = 'dulank:audit-data {--json : Aggregate JSON without personal data} {--strict : Fail on warnings too}';

    protected $description = 'Read-only audit of master data, foreign keys, duplicates and missing relations';

    public function handle(): int
    {
        $counts = [];
        $issues = [];
        $schema = Schema::connection(DB::getDefaultConnection());
        $tables = $schema->getTableListing($schema->getCurrentSchemaName(), false);
        sort($tables);
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
            foreach ($schema->getForeignKeys($table) as $foreign) {
                $parentTable = $foreign['foreign_schema'] ? $foreign['foreign_schema'].'.'.$foreign['foreign_table'] : $foreign['foreign_table'];
                $query = DB::table($table.' as child')->leftJoin($parentTable.' as parent', function ($join) use ($foreign): void {
                    foreach ($foreign['columns'] as $index => $column) {
                        $join->on('child.'.$column, '=', 'parent.'.$foreign['foreign_columns'][$index]);
                    }
                });
                foreach ($foreign['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }
                $count = $query->whereNull('parent.'.$foreign['foreign_columns'][0])->count();
                if ($count > 0) {
                    $issues[] = ['severity' => 'error', 'check' => 'orphan_foreign_key', 'table' => $table, 'field' => implode(',', $foreign['columns']), 'count' => $count];
                }
            }
        }
        foreach (config('data_contracts.masters') as $domain => $masters) {
            foreach ($masters as $table) {
                if (! isset($counts[$table]) || $counts[$table] === 0) {
                    $issues[] = ['severity' => 'warning', 'check' => 'empty_master', 'table' => $table, 'domain' => $domain, 'count' => 0];
                }
            }
        }
        foreach (config('data_contracts.duplicate_keys') as $table => $columns) {
            if (! isset($counts[$table])) {
                continue;
            }
            $query = DB::table($table)->selectRaw('COUNT(*) AS occurrences');
            foreach ($columns as $column) {
                $query->whereNotNull($column)->groupByRaw('LOWER(TRIM('.DB::connection()->getQueryGrammar()->wrap($column).'))');
            }
            $count = DB::query()->fromSub($query->havingRaw('COUNT(*) > 1'), 'duplicates')->count();
            if ($count > 0) {
                $issues[] = ['severity' => 'warning', 'check' => 'duplicate_key_groups', 'table' => $table, 'field' => implode(',', $columns), 'count' => $count];
            }
        }
        foreach (['expected_links' => 'missing_link', 'non_negative' => 'negative_value', 'schema_gaps' => 'schema_gap'] as $group => $check) {
            foreach (config('data_contracts.'.$group) as $table => $columns) {
                if (! isset($counts[$table])) {
                    continue;
                }
                foreach ($columns as $column) {
                    $exists = $schema->hasColumn($table, $column);
                    if ($group === 'schema_gaps') {
                        if (! $exists) {
                            $issues[] = ['severity' => 'warning', 'check' => $check, 'table' => $table, 'field' => $column, 'count' => $counts[$table]];
                        }

                        continue;
                    }
                    if (! $exists) {
                        continue;
                    }
                    $count = $group === 'expected_links' ? DB::table($table)->whereNull($column)->count() : DB::table($table)->where($column, '<', 0)->count();
                    if ($count > 0) {
                        $issues[] = ['severity' => $group === 'non_negative' ? 'error' : 'warning', 'check' => $check, 'table' => $table, 'field' => $column, 'count' => $count];
                    }
                }
            }
        }
        foreach (config('data_contracts.allowed_values') as $table => $fields) {
            if (! isset($counts[$table])) {
                continue;
            }
            foreach ($fields as $column => $values) {
                if (! $schema->hasColumn($table, $column)) {
                    continue;
                }
                $count = DB::table($table)->whereNotNull($column)->whereNotIn($column, $values)->count();
                if ($count > 0) {
                    $issues[] = ['severity' => 'error', 'check' => 'invalid_enum', 'table' => $table, 'field' => $column, 'count' => $count];
                }
            }
        }
        foreach (config('data_contracts.polymorphic') as $table => [$prefix, $allowed]) {
            if (! isset($counts[$table])) {
                continue;
            }
            foreach (DB::table($table)->distinct()->pluck($prefix.'_type') as $type) {
                $modelClass = Relation::getMorphedModel($type) ?? $type;
                $query = DB::table($table)->where($prefix.'_type', $type);
                $count = in_array($modelClass, $allowed, true)
                    ? $query->whereNotIn($prefix.'_id', DB::table((new $modelClass)->getTable())->select('id'))->count()
                    : $query->count();
                if ($count > 0) {
                    $issues[] = ['severity' => 'error', 'check' => 'orphan_polymorphic', 'table' => $table, 'field' => $prefix, 'count' => $count];
                }
            }
        }
        $ran = isset($counts['migrations']) ? DB::table('migrations')->pluck('migration')->all() : [];
        $pending = array_values(array_diff(array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob(database_path('migrations/*.php'))), $ran));
        if ($pending !== []) {
            $issues[] = ['severity' => 'error', 'check' => 'pending_migrations', 'count' => count($pending)];
        }
        $report = [
            'contractVersion' => config('data_contracts.version'), 'driver' => DB::connection()->getDriverName(),
            'generatedAt' => now()->utc()->toIso8601String(), 'readOnly' => true, 'counts' => $counts, 'issues' => $issues,
            'summary' => ['tables' => count($counts), 'errors' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'error')), 'warnings' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'warning'))],
        ];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Table', 'Rows'], collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->all());
            $this->table(['Severity', 'Check', 'Table', 'Field', 'Count'], array_map(fn (array $issue): array => [$issue['severity'], $issue['check'], $issue['table'] ?? '-', $issue['field'] ?? '-', $issue['count']], $issues));
        }

        return $report['summary']['errors'] > 0 || ($this->option('strict') && $report['summary']['warnings'] > 0) ? self::FAILURE : self::SUCCESS;
    }
}

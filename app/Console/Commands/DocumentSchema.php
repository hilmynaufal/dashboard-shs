<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DocumentSchema extends Command
{
    protected $signature = 'app:document-schema';

    protected $description = 'Export ERD and schema dictionary from the actual migrated MySQL schema (metadata only).';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Dokumentasi K-4 memerlukan skema MySQL yang telah dimigrasikan.');

            return self::FAILURE;
        }

        $tables = [];
        foreach (Schema::getTables(DB::connection()->getDatabaseName()) as $table) {
            $name = $table['name'];
            $tables[$name] = [
                'columns' => Schema::getColumns($name),
                'indexes' => Schema::getIndexes($name),
                'foreign_keys' => array_map(function (array $key): array {
                    unset($key['foreign_schema']);

                    return $key;
                }, Schema::getForeignKeys($name)),
            ];
        }
        ksort($tables);
        $diagram = ["erDiagram"];
        $dictionary = ["# Kamus skema MySQL", '', 'Dihasilkan oleh `php artisan app:document-schema` dari metadata skema yang benar-benar dimigrasikan.', '', 'Tidak memuat baris data, kredensial, nama database lingkungan, atau identitas nyata.', ''];
        $relations = [];
        foreach ($tables as $name => $table) {
            $diagram[] = "    {$name} {";
            $primary = [];
            foreach ($table['indexes'] as $index) {
                if ($index['primary']) {
                    $primary = array_merge($primary, $index['columns']);
                }
            }
            $foreign = array_merge([], ...array_column($table['foreign_keys'], 'columns'));
            $dictionary = array_merge($dictionary, ["## {$name}", '', '| Kolom | Tipe MySQL | Boleh kosong | Kunci |', '| --- | --- | --- | --- |']);
            foreach ($table['columns'] as $column) {
                $key = in_array($column['name'], $primary, true) ? 'PK' : (in_array($column['name'], $foreign, true) ? 'FK' : '');
                $diagram[] = '        '.preg_replace('/[^a-zA-Z0-9_]/', '_', $column['type_name']).' '.$column['name'].($key ? ' '.$key : '');
                $dictionary[] = '| '.$column['name'].' | '.str_replace('|', '\\|', $column['type']).' | '.($column['nullable'] ? 'Ya' : 'Tidak').' | '.$key.' |';
            }
            $diagram[] = '    }';
            $dictionary[] = '';
            foreach ($table['indexes'] as $index) {
                if ($index['unique']) {
                    $dictionary[] = '- Unik: `'.implode(', ', $index['columns']).'`.';
                }
            }
            foreach ($table['foreign_keys'] as $fk) {
                $nullable = false;
                foreach ($table['columns'] as $column) {
                    if (in_array($column['name'], $fk['columns'], true) && $column['nullable']) {
                        $nullable = true;
                    }
                }
                $parent = $fk['foreign_table'];
                $relations[] = '    '.$parent.($nullable ? ' |o' : ' ||').'--o{ '.$name.' : "'.implode('_', $fk['columns']).'"';
                $dictionary[] = '- Relasi `'.implode(', ', $fk['columns']).'` → `'.$parent.'('.implode(', ', $fk['foreign_columns']).')`, hapus: '.$fk['on_delete'].'.';
            }
            $dictionary[] = '';
        }
        $diagram = array_merge($diagram, $relations);
        File::ensureDirectoryExists(base_path('docs'));
        File::put(base_path('docs/erd.mmd'), implode("\n", $diagram)."\n");
        File::put(base_path('docs/schema-dictionary.md'), implode("\n", $dictionary)."\n");
        File::put(base_path('docs/schema-metadata.json'), json_encode($tables, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(count($tables).' tabel dan '.count($relations).' relasi diekspor; hanya metadata, tanpa data pengguna.');

        return self::SUCCESS;
    }
}

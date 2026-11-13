<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ZiInstrument
{
    public const VERSIONS = ['zi-manajemen-v1', 'zi-v2'];
    public const VERSION = 'zi-v2';
    public const STATES = ['draft'=>'Draf unit', 'review'=>'Dalam reviu TPI', 'final'=>'Final area'];
    public const ACTIONS = ['draft'=>'Draf tersimpan', 'submitted'=>'Menunggu reviu', 'revised'=>'Menunggu reviu ulang', 'returned'=>'Perlu perbaikan', 'rated'=>'Selesai dinilai'];

    /**
     * Instrumen terbit dibaca dari basis data; berkas di resources/instruments menjadi cadangan
     * untuk lingkungan yang belum menjalankan migrasi zi_instruments.
     */
    private static function load(string $version, string $suffix): array
    {
        if (Schema::hasTable('zi_instruments')) {
            $json = DB::table('zi_instruments')->where('version', $version)->where('status', 'published')
                ->value($suffix === '' ? 'items' : 'rules');
            if ($json !== null) {
                return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            }
        }
        abort_unless(in_array($version, self::VERSIONS, true), 409, 'Versi instrumen belum tersedia.');
        return json_decode(file_get_contents(resource_path('instruments/'.$version.$suffix.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Versi terbit yang boleh dipakai penilaian baru, terbaru di akhir. */
    public static function published(): array
    {
        return Schema::hasTable('zi_instruments')
            ? DB::table('zi_instruments')->where('status', 'published')->orderBy('published_at')->orderBy('id')->pluck('version')->all()
            : self::VERSIONS;
    }

    public static function items(string $version): array
    {
        return self::load($version, '');
    }

    public static function display(array $answer): string
    {
        if (isset($answer['choice'])) {
            return $answer['choice'];
        }
        $percent = number_format(100 * self::percent($answer), 2, ',', '.');
        if (isset($answer['previous'])) {
            return sprintf('%d → %d (penurunan %s%%)', $answer['previous'], $answer['current'], $percent);
        }
        return sprintf('%d / %d (%s%%)', $answer['numerator'], $answer['denominator'], $percent);
    }

    /** Capaian aktual sebuah jawaban persentase, belum dibatasi. */
    public static function percent(array $answer): float
    {
        if (isset($answer['previous'])) {
            // Mengikuti LKE: tahun ini nol = 100%; tahun lalu nol tetapi tahun ini ada = 0%; kenaikan = 0%.
            if ($answer['current'] === 0) {
                return 1.0;
            }
            return $answer['previous'] === 0 ? 0.0 : max(0.0, ($answer['previous'] - $answer['current']) / $answer['previous']);
        }
        return $answer['numerator'] / $answer['denominator'];
    }

    public static function rules(string $version = self::VERSION): array
    {
        return self::load($version, '.rules');
    }

    public static function result(array $items, Collection $latest, string $target, string $version = self::VERSION): array
    {
        $rules = self::rules($version);
        $scores = [];
        foreach ($latest as $entry) {
            if ($entry->action !== 'rated') {
                continue;
            }
            $answer = json_decode($entry->answer, true, flags: JSON_THROW_ON_ERROR);
            $type = $items[$entry->item]['type'];
            $scores[$entry->item] = $type === '%'
                ? min($rules['percent']['cap'], self::percent($answer))
                : $rules['scales'][$type][$answer['choice']];
        }
        $groups = [];
        $areas = [];
        foreach ($rules['areas'] as $area) {
            $values = self::groupValues($area['groups'], $scores);
            $ids = array_merge(...array_column($area['groups'], 'items'));
            $complete = count(array_intersect_key($scores, array_flip($ids))) === count($ids);
            foreach ($area['groups'] as $g) {
                $groups[$items[$g['items'][0]]['group']] = $values[$g['key']];
            }
            $total = $complete ? array_sum(array_intersect_key($values, array_column($area['groups'], 'key', 'key'))) : null;
            $minimum = round($area['max'] * $rules['thresholds'][$target], 12);
            $areas[] = ['key'=>$area['key'],'name'=>$area['name'],'max'=>$area['max'],'total'=>$total,'minimum'=>$minimum,
                'complete'=>$complete,'met'=>$complete && round($total, 12) >= $minimum];
        }
        $complete = count($scores) === count($items) && !in_array(false, array_column($areas, 'complete'), true);
        return [
            'groups'=>$groups, 'areas'=>$areas, 'scores'=>$scores, 'complete'=>$complete,
            'max'=>array_sum(array_column($areas, 'max')),
            'minimum'=>array_sum(array_column($areas, 'minimum')),
            'total'=>$complete ? array_sum(array_column($areas, 'total')) : null,
            'met'=>$complete && !in_array(false, array_column($areas, 'met'), true),
        ];
    }

    /** Subtotal berbobot per kelompok; kelompok dengan "include" menunggu subtotal kelompok lain. */
    private static function groupValues(array $groups, array $scores): array
    {
        $values = [];
        $pending = $groups;
        while ($pending) {
            $before = count($pending);
            foreach ($pending as $i => $g) {
                if (array_diff($g['include'] ?? [], array_keys($values))) {
                    continue;
                }
                $parts = array_merge(array_values(array_intersect_key($scores, array_flip($g['items']))),
                    array_map(fn ($k) => $values[$k], $g['include'] ?? []));
                $need = count($g['items']) + count($g['include'] ?? []);
                $values[$g['key']] = count($parts) === $need && !in_array(null, $parts, true) ? array_sum($parts) * $g['weight'] / $need : null;
                unset($pending[$i]);
            }
            abort_if(count($pending) === $before, 500, 'Aturan instrumen memuat ketergantungan melingkar.');
        }
        return $values;
    }
}

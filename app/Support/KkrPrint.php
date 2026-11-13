<?php

namespace App\Support;

use App\Authorization\RolePermissions;
use App\Models\{ItemReview, KkrVersion, ReviewNote};
use Illuminate\Support\Facades\DB;

/** Versioned presentation contract shared verbatim by HTML and Word. Never read live masters. */
final class KkrPrint
{
    public const TEMPLATE = 'instansi-shs-v1';
    public const LABEL = 'KKR instansi · delapan kolom';
    public const HEADERS = ['No.', 'Kode', 'Uraian', 'Spesifikasi', 'Satuan', 'Harga', 'Pengusul', 'Catatan Hasil Reviu'];

    public static function model(KkrVersion $version): array
    {
        abort_unless(in_array($version->template_version, [self::TEMPLATE, 'supervision-v1'], true), 409, 'Template versi KKR belum didukung. Hubungi pengelola.');
        $s=$version->snapshot; $a=$s['source']['assignment']; $rows=[];
        foreach ($s['source']['entries'] as $index=>$entry) {
            $v=$entry['reviewed_version']; $r=$entry['review']; $lines=['Versi diperiksa: '.$v['version_number'], 'Pemeriksa: '.$r['reviewed_by_name']];
            // Isian yang kosong tidak ditampilkan; hanya yang terisi.
            if ($r['comparison_price']!==null) { $lines[]='Harga pembanding: '.self::money($r['comparison_price']); }
            if ($r['recommended_price']!==null) { $lines[]='Rekomendasi harga: '.self::money($r['recommended_price']); }
            // Mode tunggal: satu hasil untuk semua aspek; catatan dan rekomendasi tersimpan pada aspek pertama.
            $results=($r['result_mode']??'per_aspect')==='single'?array_slice($r['results'],0,1):$r['results'];
            foreach ($results as $result) {
                $label=ItemReview::RESULTS[$result['result']]??$result['result'];
                $lines[]=($r['result_mode']??'per_aspect')==='single'?'Hasil pemeriksaan: '.$label:$result['criterion']['name'].': '.$label;
                if ($result['reason']) { $lines[]='Catatan / alasan: '.$result['reason']; }
                if ($result['recommendation']) { $lines[]='Rekomendasi: '.$result['recommendation']; }
            }
            if (trim((string)($r['recommendation']??''))!=='') { $lines[]='Rekomendasi item: '.$r['recommendation']; }
            if ($entry['latest_submitted_version']['id']!==$v['id']) {
                $latest=$entry['latest_submitted_version'];
                $lines[]='Usulan terkirim terbaru: versi '.$latest['version_number'].' · '.$latest['description'].' · '.self::money($latest['proposed_price']).'. Penilaian tetap merujuk versi diperiksa.';
            }
            foreach ($entry['notes'] as $note) {
                $lines[]=$note['category'].' · '.(ReviewNote::LABELS[$note['status']]??$note['status']).': '.$note['description'];
                if ($note['recommendation']) { $lines[]='Rekomendasi catatan: '.$note['recommendation']; }
                foreach ($note['responses'] as $response) {
                    $lines[]='Tanggapan '.$response['responded_by_name'].': '.$response['response'];
                    if ($response['decision']) { $lines[]=($response['decision']==='resolved'?'Selesai ditindaklanjuti: ':'Masih perlu perbaikan: ').$response['reason']; }
                }
            }
            $rows[]=[(string)($index+1),$v['reference_snapshot']['classification']['code']??'Belum tercatat',$v['description'],$v['specification'],
                $v['reference_snapshot']['unit']['name']??'Belum tercatat',self::money($v['proposed_price']),$a['organization']['name'],implode("\n",$lines)];
        }
        $supervision=[];
        foreach (DB::table('kkr_approvals')->where('kkr_version_id',$version->id)->orderBy('id')->get() as $decision) {
            $member=collect($a['team'])->first(fn($m)=>(int)$m['user_id']===(int)$decision->decided_by);
            $supervision[]=['level'=>$decision->level==='team_lead'?'Reviu Ketua Tim':'Reviu Pengendali Teknis',
                'text'=>($decision->decision==='approved'?'Disetujui':'Dikembalikan').' · '.($member['name']??'Pelaku tidak tercatat pada versi ini')."\n".($decision->notes??'Tanpa catatan tambahan')];
        }
        return self::clean(['template'=>self::TEMPLATE,'version_id'=>$version->id,'version_number'=>$version->version_number,'source_sha256'=>$version->snapshot_sha256,
            'title'=>'KERTAS KERJA REVIU','institution'=>'Inspektorat Daerah Kabupaten Bandung','subject'=>$s['title'],
            'facts'=>[['Nomor KKR',$s['number']],['Versi',(string)$version->version_number],['Nama Obrik',$a['organization']['name']],
                ['Tahun Anggaran',(string)$a['period']['budget_year']],['Periode',$a['period']['name']],['Penugasan',$a['number'].' · '.$a['title']],
                ['Dasar pemeriksaan',$a['task_basis']?:'Belum dicatat'],['Ruang lingkup',$a['scope_description']?:'Sesuai item dalam versi ini']],
            'purpose'=>$s['purpose']??'Belum dicatat','work_steps'=>$s['work_steps']??'Belum dicatat','headers'=>self::HEADERS,'rows'=>$rows,
            'conclusion'=>$s['conclusion'],'recommendation'=>$s['recommendation'],'supervision'=>$supervision,
            'team'=>array_map(fn($m)=>['role'=>RolePermissions::ROLES[$m['role']]??$m['role'],'name'=>$m['name']],$a['team'])]);
    }

    private static function money(?string $value): string { return $value===null?'Belum diisi':ProposalMoney::display($value); }
    private static function clean(mixed $value): mixed
    {
        if (is_array($value)) { return array_map(self::clean(...),$value); }
        return is_string($value)?preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u','',str_replace(["\r\n","\r"],"\n",$value)):$value;
    }
}

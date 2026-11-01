<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProposalImport
{
    public const FIELDS=['name'=>'Uraian','spec'=>'Spesifikasi','code'=>'Kode barang','account'=>'Rekening','unit'=>'Satuan','price'=>'Harga'];
    private const ALIASES=['name'=>['uraian','nama','nama barang','uraian barang','nama barang/jasa','uraian barang/jasa','uraian biaya'],
        'spec'=>['spesifikasi','spesifikasi barang','spesifikasi barang/jasa'],'code'=>['kode','kode barang','kode barang/jasa'],
        'account'=>['rekening','kode rekening','rekening belanja'],'unit'=>['satuan'],'price'=>['harga','harga satuan','harga usulan','harga satuan (rp)']];

    public static function detect(array $rows): array
    {
        $first=array_key_first($rows);$best=[];$score=-1;
        foreach(array_slice($rows,0,30,true)as$number=>$cells){$map=[];foreach(self::FIELDS as$field=>$label){$map[$field]=-1;foreach($cells as$column=>$cell){if(in_array(self::header($cell['value']),self::ALIASES[$field],true)){$map[$field]=$column;break;}}}$matches=count(array_filter($map,fn($c)=>$c>=0));if($matches>$score){$score=$matches;$first=$number;$best=$map;}}

        return ['header'=>$first,'columns'=>$best];
    }

    public static function state(object $batch): array
    {
        return ['id'=>$batch->id,'file_sha256'=>$batch->file_sha256,'sheet_name'=>$batch->sheet_name,'status'=>$batch->status,'mapping'=>$batch->mapping];
    }

    public static function classify(object $row,array $mapping,ProposalItemInput $lookup): array
    {
        $raw=json_decode($row->raw_values,true,512,JSON_THROW_ON_ERROR);$data=[];$issues=[];
        foreach(self::FIELDS as$field=>$label){$column=$mapping['columns'][$field]??-1;$cell=$raw['cells'][$column]??['value'=>'','type'=>'s','formula'=>false];
            $data[$field]=array_key_exists($field,$raw['corrections']??[])?(string)$raw['corrections'][$field]:$cell['value'];
            if(!array_key_exists($field,$raw['corrections']??[])){
                if($cell['formula']){$issues[]=$label.': formula tidak dihitung; gunakan nilai atau koreksi baris ini.';}
                if(in_array($field,['code','account'],true)&&trim($cell['value'])!==''&&$cell['type']==='n'){$issues[]=$label.': format kolom Excel sebagai teks agar kode tetap utuh.';}
            }
        }
        $kind='item';$messages=[];
        if($row->row_number<=$mapping['header']){$kind='header';}
        elseif($raw['skip']??false){$kind='skip';}
        elseif(!($raw['force_item']??false)){
            $headers=0;foreach(self::FIELDS as$field=>$label){if(in_array(self::header($data[$field]),self::ALIASES[$field],true)){$headers++;}}
            if($headers>=3){$kind='header';}
            elseif(trim($data['price'])===''&&trim($data['spec'])===''&&trim($data['account'])===''&&trim($data['unit'])===''){$kind='group';}
        }
        $normalized=null;
        if($kind==='item'){
            try{$normalized=$lookup->validate($data,true);}catch(ValidationException$error){$messages=array_merge(...array_values($error->errors()));}
            $messages=array_merge($issues,$messages);
        }

        return ['kind'=>$kind,'messages'=>$messages,'fields'=>$data,'normalized'=>$normalized];
    }

    public static function refresh(int $batchId): array
    {
        $batch=DB::table('import_batches')->find($batchId);$mapping=json_decode($batch->mapping,true);$lookup=new ProposalItemInput;$counts=['valid'=>0,'invalid'=>0,'ignored'=>0];
        foreach(DB::table('import_rows')->where('import_batch_id',$batchId)->orderBy('row_number')->cursor()as$row){$result=self::classify($row,$mapping,$lookup);$counts[$result['kind']!=='item'?'ignored':($result['messages']?'invalid':'valid')]++;unset($result['normalized']);DB::table('import_rows')->where('id',$row->id)->update(['errors'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now()]);}
        $summary=json_decode($batch->result_summary??'{}',true);DB::table('import_batches')->where('id',$batchId)->update(['result_summary'=>json_encode(array_replace($summary,$counts),JSON_THROW_ON_ERROR),'updated_at'=>now()]);

        return $counts;
    }

    private static function header(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u',' ',$value)));
    }
}

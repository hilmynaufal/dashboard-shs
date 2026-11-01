<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Support\{CatalogAudit, ProposalDraft, ProposalImport, ProposalItemInput, ProposalXlsx, RecordVersion, UploadLimits};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

class ProposalImportController extends Controller
{
    public function create(Proposal $proposal): View
    {
        Gate::authorize('update',$proposal);

        return view('proposals.import-upload',compact('proposal'));
    }

    public function upload(Request $request,Proposal $proposal): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);
        $request->validate(['file'=>['required','file','max:'.UploadLimits::kilobytes(5120),'extensions:xlsx'],'sheet_index'=>['required','integer','between:0,99']],
            ['file.max'=>'Berkas Excel maksimal '.UploadLimits::label(5120).'.','file.extensions'=>'Gunakan berkas .xlsx.','file.uploaded'=>'Berkas gagal diunggah; periksa batas ukuran server.','required'=>':attribute wajib diisi.']);
        $file=$request->file('file');$hash=hash_file('sha256',$file->getRealPath());
        $source=(new ProposalXlsx)->read($file->getRealPath(),(int)$request->input('sheet_index'));
        $batchId=DB::transaction(function()use($request,$proposal,$file,$hash,$source):int{
            $locked=ProposalDraft::lock($request->user(),$proposal->id);
            $existing=DB::table('import_batches')->where('proposal_id',$locked->id)->where('file_sha256',$hash)->where('sheet_name',$source['sheet'])->first();
            if($existing){if($existing->status==='failed'){$oldMapping=json_decode($existing->mapping,true);$oldMapping['revision']=Str::uuid()->toString();DB::table('import_batches')->where('id',$existing->id)->update(['status'=>'preview','mapping'=>json_encode($oldMapping),'updated_at'=>now()]);ProposalImport::refresh($existing->id);}return $existing->id;}
            if(DB::table('import_batches')->where('proposal_id',$locked->id)->where('status','preview')->count()>=5){throw ValidationException::withMessages(['file'=>'Simpan atau batalkan pratinjau sebelumnya; maksimal lima pratinjau aktif per paket.']);}
            $mapping=ProposalImport::detect($source['rows']);$mapping['revision']=Str::uuid()->toString();
            $id=DB::table('import_batches')->insertGetId(['proposal_id'=>$locked->id,'created_by'=>$request->user()->id,'file_sha256'=>$hash,'sheet_name'=>$source['sheet'],'status'=>'preview',
                'mapping'=>json_encode($mapping,JSON_THROW_ON_ERROR),'result_summary'=>json_encode(['file_name'=>mb_substr(basename(str_replace('\\','/',mb_scrub($file->getClientOriginalName(),'UTF-8'))),0,240),
                    'last_column'=>max(array_map(fn($cells)=>max(array_keys($cells)),$source['rows']))]),'created_at'=>now(),'updated_at'=>now()]);
            foreach(array_chunk($source['rows'],20,true)as$chunk){$records=[];foreach($chunk as$number=>$cells){$records[]=['import_batch_id'=>$id,'row_number'=>$number,'raw_values'=>json_encode(['cells'=>$cells],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()];}DB::table('import_rows')->insert($records);}
            ProposalImport::refresh($id);
            CatalogAudit::record($request->user()->id,'import.preview_created','import_batch',$id,null,['proposal_id'=>$locked->id,'rows'=>count($source['rows'])]);

            return $id;
        },3);

        return $request->expectsJson()?response()->json(['batch_id'=>$batchId,'message'=>'Pratinjau tersedia. Berkas yang sama memakai batch yang sudah ada.'],201):redirect()->route('proposals.import.show',[$proposal,$batchId]);
    }

    public function show(Request $request,Proposal $proposal,int $batch): View
    {
        $this->owner($proposal);$record=$this->batch($proposal,$batch);$mapping=json_decode($record->mapping,true);
        $rows=DB::table('import_rows')->where('import_batch_id',$batch)->orderBy('row_number')->paginate(25)->withQueryString();
        $header=DB::table('import_rows')->where('import_batch_id',$batch)->where('row_number',$mapping['header'])->first();

        return view('proposals.import-preview',['proposal'=>$proposal,'batch'=>$record,'mapping'=>$mapping,'rows'=>$rows,
            'headerCells'=>$header?json_decode($header->raw_values,true)['cells']:[],'summary'=>json_decode($record->result_summary,true),
            'recordVersion'=>RecordVersion::of(ProposalImport::state($record))]);
    }

    public function mapping(Request $request,Proposal $proposal,int $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);$this->batch($proposal,$batch);
        $data=$request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/'],'header'=>['required','integer','between:1,1048576',Rule::exists('import_rows','row_number')->where('import_batch_id',$batch)],
            'columns'=>['required','array:name,spec,code,account,unit,price'],'columns.*'=>['required','integer','between:0,59']],
            ['required'=>'Lengkapi pemetaan :attribute.','between'=>'Pilih nomor baris/kolom yang tersedia.','exists'=>'Baris judul tidak ditemukan.']);
        foreach(array_keys(ProposalImport::FIELDS)as$field){if(!array_key_exists($field,$data['columns'])){throw ValidationException::withMessages(['columns'=>'Petakan seluruh enam kolom sebelum melanjutkan.']);}}
        DB::transaction(function()use($request,$proposal,$batch,$data):void{
            ProposalDraft::lock($request->user(),$proposal->id);$record=$this->preview($proposal,$batch);RecordVersion::assertCurrent($data['record_version'],ProposalImport::state($record));
            DB::table('import_batches')->where('id',$batch)->update(['mapping'=>json_encode(['header'=>(int)$data['header'],'columns'=>array_map('intval',$data['columns']),'revision'=>Str::uuid()->toString()]),'updated_at'=>now()]);
            ProposalImport::refresh($batch);
        });

        return $this->done($request,$proposal,$batch,'Pemetaan dan pemeriksaan diperbarui.');
    }

    public function rowForm(Proposal $proposal,int $batch,int $row): View
    {
        Gate::authorize('update',$proposal);$record=$this->preview($proposal,$batch);$entry=$this->row($batch,$row);
        $result=ProposalImport::classify($entry,json_decode($record->mapping,true),new ProposalItemInput);

        return view('proposals.import-row',['proposal'=>$proposal,'batch'=>$record,'row'=>$entry,'fields'=>$result['fields'],'recordVersion'=>RecordVersion::of(ProposalImport::state($record))]);
    }

    public function rowUpdate(Request $request,Proposal $proposal,int $batch,int $row): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);
        $data=$request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/'],'operation'=>['required',Rule::in(['correct','skip','restore'])],
            'fields'=>['sometimes','array:name,spec,code,account,unit,price'],'fields.*'=>['nullable','string','max:10000']]);
        DB::transaction(function()use($request,$proposal,$batch,$row,$data):void{
            ProposalDraft::lock($request->user(),$proposal->id);$record=$this->preview($proposal,$batch);RecordVersion::assertCurrent($data['record_version'],ProposalImport::state($record));$entry=$this->row($batch,$row);
            $raw=json_decode($entry->raw_values,true);$raw['skip']=$data['operation']==='skip';
            if($data['operation']!=='skip'){$raw['force_item']=true;}
            if($data['operation']==='correct'){$raw['corrections']=array_replace(array_fill_keys(array_keys(ProposalImport::FIELDS),''),$data['fields']??[]);}
            DB::table('import_rows')->where('id',$row)->update(['raw_values'=>json_encode($raw,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
            $mapping=json_decode($record->mapping,true);$mapping['revision']=Str::uuid()->toString();DB::table('import_batches')->where('id',$batch)->update(['mapping'=>json_encode($mapping),'updated_at'=>now()]);
            ProposalImport::refresh($batch);
            CatalogAudit::record($request->user()->id,'import.row_'.$data['operation'],'import_batch',$batch,null,['row_number'=>$entry->row_number]);
        });

        return $this->done($request,$proposal,$batch,'Baris diperbarui. Data belum masuk ke paket sebelum Anda menyimpan impor.');
    }

    public function commit(Request $request,Proposal $proposal,int $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);$request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']]);
        $error=DB::transaction(function()use($request,$proposal,$batch):?string{
            $locked=ProposalDraft::lock($request->user(),$proposal->id);$record=$this->batch($locked,$batch,true);
            if($record->status==='completed'){return null;}
            abort_unless($record->status==='preview',409);RecordVersion::assertCurrent($request->input('record_version'),ProposalImport::state($record));
            $counts=ProposalImport::refresh($batch);
            if($counts['invalid']||!$counts['valid']){return 'Perbaiki atau lewati semua baris bermasalah, dan sisakan sedikitnya satu item valid.';}
            $lookup=new ProposalItemInput;$mapping=json_decode($record->mapping,true);
            foreach(DB::table('import_rows')->where('import_batch_id',$batch)->orderBy('row_number')->cursor()as$row){$result=ProposalImport::classify($row,$mapping,$lookup);if($result['kind']!=='item')continue;
                if($result['messages']){throw ValidationException::withMessages(['import'=>'Referensi berubah. Periksa ulang pratinjau.']);}
                $version=ProposalDraft::append($locked,$request->user(),$result['normalized']);DB::table('import_rows')->where('id',$row->id)->update(['item_version_id'=>$version,'updated_at'=>now()]);}
            DB::table('import_batches')->where('id',$batch)->update(['status'=>'completed','updated_at'=>now()]);
            CatalogAudit::record($request->user()->id,'import.completed','import_batch',$batch,null,['proposal_id'=>$locked->id,'items'=>$counts['valid']]);

            return null;
        },3);
        if($error){throw ValidationException::withMessages(['import'=>$error]);}

        return $this->done($request,$proposal,$batch,'Impor tersimpan. Impor ulang berkas/lembar yang sama tidak menambahkan item ganda.');
    }

    public function cancel(Request $request,Proposal $proposal,int $batch): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);$request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']]);
        DB::transaction(function()use($request,$proposal,$batch):void{ProposalDraft::lock($request->user(),$proposal->id);$record=$this->preview($proposal,$batch);RecordVersion::assertCurrent($request->input('record_version'),ProposalImport::state($record));DB::table('import_batches')->where('id',$batch)->update(['status'=>'failed','updated_at'=>now()]);CatalogAudit::record($request->user()->id,'import.cancelled','import_batch',$batch,null,['proposal_id'=>$proposal->id]);});

        return $request->expectsJson()?response()->json(['message'=>'Pratinjau dibatalkan.']):redirect()->route('proposals.show',$proposal)->with('status','Pratinjau dibatalkan.');
    }

    private function owner(Proposal $proposal): void
    {
        Gate::authorize('view',$proposal);abort_unless(auth()->user()->hasPermission('proposals.view_own')&&(int)auth()->user()->organization_id===(int)$proposal->organization_id,403);
    }

    private function batch(Proposal $proposal,int $id,bool $lock=false): object
    {
        $query=DB::table('import_batches')->where('proposal_id',$proposal->id)->where('id',$id);if($lock)$query->lockForUpdate();$batch=$query->first();abort_unless($batch,404);return $batch;
    }

    private function preview(Proposal $proposal,int $batch): object
    {
        $record=$this->batch($proposal,$batch,true);abort_unless($record->status==='preview',409);return $record;
    }

    private function row(int $batch,int $id): object
    {
        $row=DB::table('import_rows')->where('import_batch_id',$batch)->where('id',$id)->first();abort_unless($row,404);return $row;
    }

    private function done(Request $request,Proposal $proposal,int $batch,string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()?response()->json(['message'=>$message,'batch_id'=>$batch]):redirect()->route('proposals.import.show',[$proposal,$batch])->with('status',$message);
    }
}

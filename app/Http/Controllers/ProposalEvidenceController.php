<?php

namespace App\Http\Controllers;

use App\Models\{EvidenceFile, Proposal};
use App\Support\{CatalogAudit, ProposalDraft, RecordVersion, UploadLimits};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalEvidenceController extends Controller
{
    public function store(Request $request,Proposal $proposal,int $item): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal); Gate::authorize('evidence.upload_own');
        $request->validate(['file'=>['required','file','mimetypes:application/pdf,image/jpeg,image/png','max:'.UploadLimits::kilobytes(10240)],
            'record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']],['file.mimetypes'=>'Bukti harus PDF, JPG atau PNG.','file.max'=>'Ukuran bukti maksimal '.UploadLimits::label(10240).'.','file.uploaded'=>'Berkas gagal diunggah; periksa ukuran dan batas unggah server.','required'=>':attribute wajib diisi.']);
        $path=null;
        try {
            $id=DB::transaction(function () use ($request,$proposal,$item,&$path): int {
                $locked=ProposalDraft::lock($request->user(),$proposal->id); $old=$this->version($locked,$item);
                RecordVersion::assertCurrent($request->input('record_version'),(array)$old);
                $upload=$request->file('file');$hash=hash_file('sha256',$upload->getRealPath());
                if (DB::table('item_version_evidence')->join('evidence_files','evidence_files.id','=','evidence_file_id')->where('item_version_id',$old->id)->where('sha256',$hash)->exists()) { throw ValidationException::withMessages(['file'=>'Berkas yang sama sudah terlampir pada versi ini.']); }
                $mime=$upload->getMimeType();$ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime];
                $path=$upload->storeAs('evidence/'.Str::uuid(),Str::uuid().'.'.$ext,'local');
                if (!$path) { throw ValidationException::withMessages(['file'=>'Penyimpanan bukti tidak tersedia. Coba lagi atau hubungi pengelola.']); }
                $name=mb_substr(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u','_',mb_scrub($upload->getClientOriginalName(),'UTF-8')),0,240);
                $id=DB::table('evidence_files')->insertGetId(['organization_id'=>$locked->organization_id,'uploaded_by'=>$request->user()->id,'disk'=>'local','path'=>$path,
                    'original_name'=>$name,'mime_type'=>$mime,'byte_size'=>$upload->getSize(),'sha256'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
                $version=ProposalDraft::copyVersion($locked,$request->user(),$item);
                DB::table('item_version_evidence')->insert(['item_version_id'=>$version,'evidence_file_id'=>$id,'purpose'=>'Bukti pendukung']);
                CatalogAudit::record($request->user()->id,'evidence.uploaded','evidence_file',$id,null,['proposal_id'=>$locked->id,'item_id'=>$item,'version_id'=>$version]);

                return $id;
            });
        } catch (\Throwable $error) { if($path){Storage::disk('local')->delete($path);}throw $error; }

        return $request->expectsJson()?response()->json(['message'=>'Bukti tersimpan pada versi baru.','id'=>$id],201):redirect()->route('proposals.items.edit',[$proposal,$item])->with('status','Bukti tersimpan pada versi baru.');
    }

    public function remove(Request $request,Proposal $proposal,int $item,int $file): JsonResponse|RedirectResponse
    {
        Gate::authorize('update',$proposal);Gate::authorize('evidence.upload_own');
        $request->validate(['record_version'=>['required','string','regex:/\A[a-f0-9]{64}\z/']]);
        DB::transaction(function () use($request,$proposal,$item,$file): void {
            $locked=ProposalDraft::lock($request->user(),$proposal->id);$old=$this->version($locked,$item);RecordVersion::assertCurrent($request->input('record_version'),(array)$old);
            abort_unless(DB::table('item_version_evidence')->where('item_version_id',$old->id)->where('evidence_file_id',$file)->exists(),404);
            $version=ProposalDraft::copyVersion($locked,$request->user(),$item);
            DB::table('item_version_evidence')->where('item_version_id',$version)->where('evidence_file_id',$file)->delete();
            CatalogAudit::record($request->user()->id,'evidence.unlinked','evidence_file',$file,null,['new_version_id'=>$version,'previous_version_id'=>$old->id]);
        });

        return $request->expectsJson()?response()->json(['message'=>'Bukti dilepas dari versi baru; versi sebelumnya tetap tersedia.']):back()->with('status','Bukti dilepas dari versi baru; arsip versi sebelumnya tetap tersedia.');
    }

    public function download(EvidenceFile $file): StreamedResponse
    {
        Gate::authorize('download',$file);
        abort_unless($file->disk==='local' && str_starts_with($file->path,'evidence/') && !str_contains($file->path,'..') && Storage::disk('local')->exists($file->path),404);

        return Storage::disk('local')->download($file->path,$file->original_name,['Content-Type'=>$file->mime_type,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; default-src 'none'"]);
    }

    private function version(Proposal $proposal,int $item): object
    {
        abort_unless(DB::table('proposal_items')->where('id',$item)->where('proposal_id',$proposal->id)->exists(),404);
        $row=DB::table('proposal_item_versions')->where('proposal_item_id',$item)->orderByDesc('version_number')->first();abort_unless($row,404);

        return $row;
    }
}

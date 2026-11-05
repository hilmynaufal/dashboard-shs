<?php

namespace App\Support;

use App\Models\{KkrVersion,User};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;

final class KkrExport
{
    /** Caller authorizes document access first. Final content and bytes are retained together, never regenerated. */
    public static function presentation(KkrVersion $version,User $actor): array
    {
        abort_unless(KkrWork::integrity($version),409,'Rekaman KKR tidak dapat diverifikasi. Hubungi pengelola.');
        if (!$version->finalized_at) { return ['model'=>KkrPrint::model($version),'bytes'=>null]; }
        return DB::transaction(function()use($version,$actor): array {
            $locked=KkrVersion::query()->lockForUpdate()->findOrFail($version->id);
            abort_unless(KkrWork::integrity($locked),409,'Rekaman KKR tidak dapat diverifikasi. Hubungi pengelola.');
            $archive=DB::table('kkr_exports')->where('kkr_version_id',$locked->id)->first();
            if ($archive) {
                $model=json_decode($archive->render_snapshot??'null',true);
                abort_unless(is_array($model) && $archive->render_sha256 && hash_equals($archive->render_sha256,KkrWork::hash($model))
                    && ($model['source_sha256']??null)===$locked->snapshot_sha256 && (int)($model['version_id']??0)===(int)$locked->id
                    && $archive->disk==='local' && preg_match('/\Akkr\/[a-f0-9-]+\.docx\z/',$archive->path),409,'Arsip Word tidak dapat diverifikasi. Hubungi pengelola; arsip tidak ditimpa.');
                $disk=Storage::disk('local');
                abort_unless($disk->exists($archive->path),409,'Arsip Word tidak tersedia. Hubungi pengelola; arsip tidak dibuat ulang.');
                $bytes=$disk->get($archive->path);
                abort_unless(is_string($bytes) && strlen($bytes)===(int)$archive->byte_size && hash_equals($archive->sha256,hash('sha256',$bytes)),409,'Arsip Word tidak dapat diverifikasi. Hubungi pengelola; arsip tidak ditimpa.');
                return ['model'=>$model,'bytes'=>$bytes];
            }
            $model=KkrPrint::model($locked);$bytes=KkrWord::bytes($model);$path='kkr/'.Str::uuid().'.docx';
            if (!Storage::disk('local')->put($path,$bytes)) { throw new \RuntimeException('Cannot retain final Word archive.'); }
            // Rollback may leave an unreferenced private file, never an accessible or overwritten final archive.
            DB::table('kkr_exports')->insert(['kkr_version_id'=>$locked->id,'generated_by'=>$actor->id,'disk'=>'local','path'=>$path,
                'sha256'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes),'render_snapshot'=>json_encode($model,JSON_THROW_ON_ERROR),
                'render_sha256'=>KkrWork::hash($model),'created_at'=>now(),'updated_at'=>now()]);
            CatalogAudit::record($actor->id,'kkr.word_archived','kkr_version',$locked->id,null,['sha256'=>hash('sha256',$bytes),'byte_size'=>strlen($bytes)]);
            return ['model'=>$model,'bytes'=>$bytes];
        },3);
    }
}

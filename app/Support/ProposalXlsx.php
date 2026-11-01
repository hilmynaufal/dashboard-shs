<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Bounded value-only XLSX reader: never extracts archives, executes formulas or resolves external XML. */
final class ProposalXlsx
{
    public const MAX_ROWS = 12000;
    public const MAX_COLUMNS = 60;
    public const MAX_EXPANDED = 33554432;

    public function read(string $path, int $sheetIndex = 0): array
    {
        if (!class_exists(\ZipArchive::class)||!class_exists(\XMLReader::class)) { $this->fail('Pembaca Excel server belum tersedia. Hubungi pengelola.'); }
        $zip=new \ZipArchive;
        if($zip->open($path)!==true){$this->fail('Berkas harus berupa Excel .xlsx yang sah.');}
        try {
            if($zip->numFiles>512){$this->fail('Isi berkas Excel terlalu kompleks.');}
            $expanded=0;
            for($i=0;$i<$zip->numFiles;$i++){$entry=$zip->statIndex($i);if(!is_array($entry)||$entry['size']<0){$this->fail('Arsip Excel tidak sah.');}$expanded+=$entry['size'];if($expanded>self::MAX_EXPANDED){$this->fail('Isi Excel setelah dibuka melebihi 32 MB. Pecah berkas menjadi beberapa bagian.');}}
            $book=$this->xml($this->entry($zip,'xl/workbook.xml'));$rels=$this->xml($this->entry($zip,'xl/_rels/workbook.xml.rels'));
            $targets=[];
            foreach((new \DOMXPath($rels))->query('//*[local-name()="Relationship"]') as $rel){
                if(str_ends_with($rel->getAttribute('Type'),'/worksheet')&&$rel->getAttribute('TargetMode')!=='External'){$targets[$rel->getAttribute('Id')]=$this->sheetPath($rel->getAttribute('Target'));}
            }
            $sheets=[];
            foreach((new \DOMXPath($book))->query('//*[local-name()="sheet"]') as $sheet){
                $rid='';foreach($sheet->attributes as $attribute){if($attribute->localName==='id'){$rid=$attribute->value;}}
                if(!isset($targets[$rid])){$this->fail('Tautan lembar Excel tidak didukung.');}
                $name=$sheet->getAttribute('name');if($name===''||mb_strlen($name)>100){$this->fail('Nama lembar Excel tidak sah.');}
                $sheets[]=['name'=>$name,'path'=>$targets[$rid]];
            }
            if(!isset($sheets[$sheetIndex])||count($sheets)>100){$this->fail('Pilih lembar Excel yang tersedia.');}
            $strings=[];$shared=$zip->locateName('xl/sharedStrings.xml');
            if($shared!==false){$this->elements($this->entry($zip,'xl/sharedStrings.xml'),'si',function($xml)use(&$strings):void{
                $strings[]=$this->text($this->xml($xml));if(count($strings)>200000){$this->fail('Daftar teks Excel terlalu besar.');}
            });}
            $rows=[];$physical=0;$previous=0;$cells=0;
            $this->elements($this->entry($zip,$sheets[$sheetIndex]['path']),'row',function($xml)use(&$rows,&$physical,&$previous,&$cells,$strings):void{
                if(++$physical>50000){$this->fail('Jumlah baris Excel terlalu besar.');}
                $doc=$this->xml($xml);$root=$doc->documentElement;$number=$root->getAttribute('r');
                $number=$number===''?$previous+1:(ctype_digit($number)?(int)$number:0);
                if($number<=$previous||$number>1048576){$this->fail('Nomor baris Excel tidak berurutan atau tidak sah.');}$previous=$number;
                $values=[];$column=0;
                foreach((new \DOMXPath($doc))->query('/*/*[local-name()="c"]') as $cell){
                    if(++$cells>250000){$this->fail('Jumlah sel Excel terlalu besar.');}$this->guardMemory();
                    $ref=$cell->getAttribute('r');$index=$column;
                    if($ref!==''){if(!preg_match('/\A([A-Z]{1,3})([0-9]+)\z/',$ref,$m)||(int)$m[2]!==$number){$this->fail('Alamat sel Excel tidak sah.');}$index=0;foreach(str_split($m[1])as$char){$index=$index*26+ord($char)-64;}$index--;}
                    $column=$index+1;$xpath=new \DOMXPath($doc);$value=$xpath->query('./*[local-name()="v"]',$cell)->item(0)?->textContent??'';
                    $type=$cell->getAttribute('t')?:'n';$formula=$xpath->query('./*[local-name()="f"]',$cell)->length>0;
                    if($type==='s'){if(!ctype_digit($value)||!array_key_exists((int)$value,$strings)){$this->fail('Daftar teks Excel tidak sesuai.');}$value=$strings[(int)$value];}
                    elseif($type==='inlineStr'){$value=$this->text($cell);}
                    if(mb_strlen($value)>10000){$this->fail('Teks satu sel melebihi 10.000 karakter.');}
                    if($index>=self::MAX_COLUMNS){if(trim($value)!==''){$this->fail('Maksimal 60 kolom berisi data.');}continue;}
                    if(isset($values[$index])){$this->fail('Alamat sel berulang dalam satu baris.');}
                    $values[$index]=['value'=>$value,'type'=>$type,'formula'=>$formula];
                }
                if(array_filter($values,fn($v)=>trim($v['value'])!=='')){$rows[$number]=$values;if(count($rows)>self::MAX_ROWS){$this->fail('Maksimal 12.000 baris berisi data per lembar.');}}
            });
            if(!$rows){$this->fail('Lembar yang dipilih tidak berisi data.');}

            return ['sheet'=>$sheets[$sheetIndex]['name'],'rows'=>$rows,'sheets'=>array_column($sheets,'name')];
        } finally {$zip->close();}
    }

    private function entry(\ZipArchive $zip,string $name): string
    {
        $stat=$zip->statName($name);if($stat===false){$this->fail('Struktur berkas Excel tidak lengkap.');}$this->guardMemory((int)$stat['size']*3);
        $value=$zip->getFromName($name);if($value===false||strlen($value)>self::MAX_EXPANDED){$this->fail('Struktur berkas Excel tidak lengkap atau terlalu besar.');}
        if(stripos($value,'<!DOCTYPE')!==false||stripos($value,'<!ENTITY')!==false||str_contains($value,"\0")){$this->fail('Deklarasi XML eksternal atau penyandian ini tidak diizinkan.');}

        return $value;
    }

    private function xml(string $xml): \DOMDocument
    {
        if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false){$this->fail('Deklarasi XML tidak diizinkan.');}
        $old=libxml_use_internal_errors(true);
        try{$doc=new \DOMDocument;if(!$doc->loadXML($xml,LIBXML_NONET|LIBXML_COMPACT)){$this->fail('XML berkas Excel tidak dapat dibaca.');}return $doc;}
        finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    }

    private function elements(string $xml,string $name,callable $consume): void
    {
        $old=libxml_use_internal_errors(true);$reader=new \XMLReader;
        try{
            if(!$reader->XML($xml,null,LIBXML_NONET|LIBXML_COMPACT)){$this->fail('XML berkas Excel tidak sah.');}
            while($reader->read()){if($reader->nodeType===\XMLReader::ELEMENT&&$reader->localName===$name){$consume($reader->readOuterXml());}}
            foreach(libxml_get_errors()as$error){if($error->level>=LIBXML_ERR_ERROR){$this->fail('XML berkas Excel terpotong atau tidak sah.');}}
        }finally{$reader->close();libxml_clear_errors();libxml_use_internal_errors($old);}
    }

    private function text(\DOMNode $node): string
    {
        $xpath=new \DOMXPath($node instanceof \DOMDocument?$node:$node->ownerDocument);$text='';
        foreach($xpath->query('.//*[local-name()="t"]',$node) as $part){$text.=$part->textContent;}

        return $text;
    }

    private function sheetPath(string $target): string
    {
        if(preg_match('/[\\\\:?#]/',$target)){$this->fail('Alamat lembar Excel tidak didukung.');}
        $parts=[];foreach(explode('/',str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target)as$part){if($part===''||$part==='.')continue;if($part==='..'){if(!$parts){$this->fail('Alamat lembar di luar workbook.');}array_pop($parts);}else{$parts[]=$part;}}
        $path=implode('/',$parts);if(!str_starts_with($path,'xl/worksheets/')||!str_ends_with($path,'.xml')){$this->fail('Alamat lembar Excel tidak didukung.');}

        return $path;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file'=>$message]);
    }

    private function guardMemory(int $extra=0):void
    {
        $raw=trim((string)ini_get('memory_limit'));$limit=(float)$raw*match(strtolower(substr($raw,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        if($limit>0&&memory_get_usage(true)+$extra>$limit*0.75){$this->fail('Excel terlalu besar untuk kapasitas pembaca saat ini. Pecah berkas menjadi beberapa bagian.');}
    }
}

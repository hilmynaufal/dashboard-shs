<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Value-only XLSX writer. Text cells preserve codes, decimal precision and formula-looking user input. */
final class ReportXlsx
{
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** A small capacity is useful for exercising the same multi-sheet path without a million-row fixture. */
    public function create(iterable $rows,int $capacity=1048575): string
    {
        abort_unless(class_exists(\ZipArchive::class),503,'Pembuat Excel belum tersedia. Hubungi pengelola.');
        if($capacity<1 || $capacity>1048575){throw new \InvalidArgumentException('Invalid worksheet capacity.');}
        $path=$this->temporary();$sheets=[];$handle=null;$zip=new \ZipArchive;$opened=false;$finished=false;
        try {
            $sheet=$this->temporary();$sheets[]=$sheet;$handle=fopen($sheet,'wb');$this->start($handle);$count=0;
            foreach($rows as $values){
                if($count===$capacity){$this->end($handle,$count+1);fclose($handle);$sheet=$this->temporary();$sheets[]=$sheet;$handle=fopen($sheet,'wb');$this->start($handle);$count=0;}
                if(count($values)!==count(ItemReport::HEADERS)){throw new \RuntimeException('Invalid report row.');}
                $this->write($handle,$this->row(array_values($values),++$count+1,false));
            }
            $this->end($handle,$count+1);fclose($handle);$handle=null;
            if($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true){throw new \RuntimeException('Cannot create Excel package.');}$opened=true;
            foreach($this->parts(count($sheets)) as $name=>$xml){if(!$zip->addFromString($name,$xml)){throw new \RuntimeException('Cannot write Excel package.');}}
            foreach($sheets as $i=>$file){if(!$zip->addFile($file,'xl/worksheets/sheet'.($i+1).'.xml')){throw new \RuntimeException('Cannot write worksheet.');}}
            if(!$zip->close()){throw new \RuntimeException('Cannot finish Excel package.');}$opened=false;$finished=true;
            return $path;
        } finally {
            if(is_resource($handle)){fclose($handle);}if($opened){$zip->close();}
            foreach($sheets as $sheet){if(is_file($sheet)){unlink($sheet);}}
            if(!$finished && is_file($path)){unlink($path);}
        }
    }

    private function start($file): void
    {
        $this->write($file,'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="'.self::NS.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>');
        foreach([7,26,17,10,34,25,40,50,16,36,29,25,16] as $i=>$width){$this->write($file,'<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>');}
        $this->write($file,'</cols><sheetData>'.$this->row(ItemReport::HEADERS,1,true));
    }
    private function end($file,int $last): void {$this->write($file,'</sheetData><autoFilter ref="A1:M'.$last.'"/></worksheet>');}
    private function row(array $values,int $row,bool $header): string
    {
        $xml='<row r="'.$row.'">';
        foreach($values as $i=>$text){
            $text=str_replace(["\r\n","\r"],"\n",(string)$text);
            // Excel interprets literal _xNNNN_ sequences; protect the underscore first, then encode forbidden XML controls.
            if(strlen(mb_convert_encoding($text,'UTF-16LE','UTF-8'))/2>32767){throw ValidationException::withMessages(['export'=>'Ada isian yang melebihi kapasitas satu sel Excel (32.767 karakter). Periksa data sebelum mengekspor.']);}
            $text=preg_replace('/_(x[0-9a-fA-F]{4}_)/','_x005F_$1',$text);
            $text=preg_replace_callback('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u',fn($m)=>sprintf('_x%04X_',mb_ord($m[0],'UTF-8')),$text);
            $xml.='<c r="'.chr(65+$i).$row.'" t="inlineStr" s="'.($header?1:0).'"><is><t xml:space="preserve">'.htmlspecialchars($text,ENT_QUOTES|ENT_XML1,'UTF-8').'</t></is></c>';
        }
        return $xml.'</row>';
    }
    private function parts(int $count): array
    {
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';$content='';$sheets='';$relations='';
        for($i=1;$i<=$count;$i++){
            $content.='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheets.='<sheet name="Rekap '.$i.'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $relations.='<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        return ['[Content_Types].xml'=>$xml.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$content.'</Types>',
            '_rels/.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>$xml.'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$sheets.'</sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>$xml.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>$xml.'<styleSheet xmlns="'.self::NS.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF292620"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4F1EB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'];
    }
    private function temporary(): string {$path=tempnam(sys_get_temp_dir(),'shs-rekap-');if($path===false){throw new \RuntimeException('Temporary export storage is unavailable.');}return $path;}
    private function write($file,string $data): void
    {
        if(!is_resource($file) || fwrite($file,$data)!==strlen($data)){throw new \RuntimeException('Cannot write temporary export data.');}
    }
}

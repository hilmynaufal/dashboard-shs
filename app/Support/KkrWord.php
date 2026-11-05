<?php

namespace App\Support;

/** Minimal DOCX package: static XML only; no macros, external relationships or embedded client files. */
final class KkrWord
{
    private const W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public static function bytes(array $model): string
    {
        abort_unless(class_exists(\ZipArchive::class),503,'Pembuat Word belum tersedia. Hubungi pengelola.');
        $path=tempnam(sys_get_temp_dir(),'kkr-');
        if ($path===false) { throw new \RuntimeException('Temporary Word storage is unavailable.'); }
        try {
            $zip=new \ZipArchive;
            if ($zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true) { throw new \RuntimeException('Cannot create Word package.'); }
            $parts=self::parts($model);
            foreach ($parts as $name=>$xml) {
                if (!$zip->addFromString($name,$xml) || !$zip->setMtimeName($name,315532800)) { $zip->close(); throw new \RuntimeException('Cannot write Word package.'); }
            }
            if (!$zip->close()) { throw new \RuntimeException('Cannot finish Word package.'); }
            $bytes=file_get_contents($path);
            if ($bytes===false) { throw new \RuntimeException('Cannot read Word package.'); }
            return $bytes;
        } finally { if (is_file($path)) { unlink($path); } }
    }

    private static function parts(array $m): array
    {
        $body=self::p($m['title'],'Title').self::p($m['institution'],'Subtitle').self::p($m['subject'],'Heading1');
        foreach ($m['facts'] as [$label,$value]) { $body.=self::p($label.': '.$value); }
        $body.=self::section('TUJUAN',$m['purpose']).self::section('LANGKAH-LANGKAH KERJA',$m['work_steps']).self::p('HASIL REVIU','Heading1');
        // Source template A4 portrait geometry and proportional eight-column grid, fitted to the usable page width.
        $widths=[493,1759,1587,1527,813,1183,1075,1768];
        $body.='<w:tbl><w:tblPr><w:tblW w:w="10205" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders>';
        foreach (['top','left','bottom','right','insideH','insideV'] as $edge) { $body.='<w:'.$edge.' w:val="single" w:sz="4" w:color="000000"/>'; }
        $body.='</w:tblBorders><w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($widths as $width) { $body.='<w:gridCol w:w="'.$width.'"/>'; }
        $body.='</w:tblGrid>'.self::row($m['headers'],$widths,true);
        foreach ($m['rows'] as $row) { $body.=self::row($row,$widths,false); }
        $body.='</w:tbl>'.self::section('KESIMPULAN',$m['conclusion']).self::section('REKOMENDASI',$m['recommendation']);
        foreach (['Reviu Ketua Tim','Reviu Pengendali Teknis'] as $level) {
            $decision=collect($m['supervision'])->firstWhere('level',$level);
            $body.=self::section(mb_strtoupper($level),$decision['text']??'Belum ada keputusan pada versi ini');
        }
        $body.=self::p('IDENTITAS TIM','Heading1');
        foreach ($m['team'] as $member) { $body.=self::p($member['role'].': '.$member['name']); }
        $body.='<w:sectPr><w:footerReference w:type="default" r:id="rIdFooter"/><w:pgSz w:w="11907" w:h="16839"/><w:pgMar w:top="964" w:right="851" w:bottom="851" w:left="851" w:header="709" w:footer="709" w:gutter="0"/><w:pgNumType w:start="1"/></w:sectPr>';
        $declaration='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        return [
            '[Content_Types].xml'=>$declaration.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>',
            '_rels/.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml'=>$declaration.'<w:document xmlns:w="'.self::W.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>',
            'word/_rels/document.xml.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/><Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>',
            'word/styles.xml'=>$declaration.'<w:styles xmlns:w="'.self::W.'"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:color w:val="000000"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="160" w:after="80"/></w:pPr><w:rPr><w:b/></w:rPr></w:style></w:styles>',
            'word/settings.xml'=>$declaration.'<w:settings xmlns:w="'.self::W.'"><w:updateFields w:val="true"/></w:settings>',
            'word/footer1.xml'=>$declaration.'<w:ftr xmlns:w="'.self::W.'"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t xml:space="preserve">Halaman </w:t></w:r><w:fldSimple w:instr="PAGE"><w:r><w:t>1</w:t></w:r></w:fldSimple></w:p></w:ftr>',
        ];
    }

    private static function section(string $title,string $text): string { return self::p($title,'Heading1').self::p($text); }
    private static function p(string $text,string $style='Normal',bool $bold=false): string
    {
        $runs='';
        foreach (explode("\n",$text) as $i=>$line) { $runs.=($i?'<w:r><w:br/></w:r>':'').'<w:r>'.($bold?'<w:rPr><w:b/></w:rPr>':'').'<w:t xml:space="preserve">'.htmlspecialchars($line,ENT_XML1|ENT_QUOTES,'UTF-8').'</w:t></w:r>'; }
        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>'.$runs.'</w:p>';
    }
    private static function row(array $cells,array $widths,bool $header): string
    {
        // Data rows deliberately split across pages: no fixed heights or cantSplit, even for a single very long finding.
        $xml='<w:tr>'.($header?'<w:trPr><w:tblHeader/></w:trPr>':'');
        foreach ($cells as $i=>$cell) { $xml.='<w:tc><w:tcPr><w:tcW w:w="'.$widths[$i].'" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>'.self::p($cell,'Normal',$header).'</w:tc>'; }
        return $xml.'</w:tr>';
    }
}

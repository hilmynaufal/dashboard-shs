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
        $facts = array_column($m['facts'], 1, 0);
        $body = self::p('FORM. 5', bold: true)
            .self::p('FORMULIR KERTAS KERJA REVIU', bold: true, properties: '<w:pBdr><w:bottom w:val="single" w:sz="6"/></w:pBdr><w:spacing w:after="120"/>');
        // Identitas, tujuan, langkah kerja, dan pita HASIL REVIU berada dalam satu kotak bergaris luar yang menyambung.
        $form = [self::row([self::cell(self::p($m['title'], bold: true), 10205, 6)]),
            self::row([self::cell(self::p($m['institution'], bold: true), 10205, 6, borders: self::edges(['bottom']))])];
        foreach ([['Nama Obrik','Nomor KKR'], ['Tahun Anggaran','Versi'], ['Periode','Penugasan']] as [$left,$right]) {
            $form[] = self::row([
                self::cell(self::p($left), self::IDENTITY[0]), self::cell(self::p(':'), self::IDENTITY[1]),
                self::cell(self::p($facts[$left] ?? ''), self::IDENTITY[2], borders: self::edges(['right'])),
                self::cell(self::p($right), self::IDENTITY[3], borders: self::edges(['left'])), self::cell(self::p(':'), self::IDENTITY[4]),
                self::cell(self::p($facts[$right] ?? ''), self::IDENTITY[5]),
            ]);
        }
        array_push($form, self::band('TUJUAN', 6), self::row([self::cell(self::p($m['purpose']), 10205, 6)]),
            self::band('LANGKAH-LANGKAH KERJA', 6), self::row([self::cell(self::p($m['work_steps']), 10205, 6)]),
            self::band('HASIL REVIU', 6));
        $body .= self::table(self::IDENTITY, $form, inner: false).self::p('', properties: '<w:spacing w:after="60"/>');
        $body .= self::section('Dasar pemeriksaan', $facts['Dasar pemeriksaan'] ?? 'Belum dicatat')
            .self::section('Ruang lingkup', $facts['Ruang lingkup'] ?? 'Belum dicatat')
            .self::heading('Kertas Kerja Reviu').self::bodyText($m['subject']);

        $widths = [450,1760,1450,1450,760,1150,1000,2185];
        $rows = [self::findingRow($m['headers'], $widths, true)];
        foreach ($m['rows'] as $row) {
            $rows[] = self::findingRow($row, $widths, false);
        }
        $body .= self::table($widths, $rows).self::section('Kesimpulan', $m['conclusion'])
            .self::section('Rekomendasi', $m['recommendation']).self::p('');
        $closing = [];
        foreach (['Reviu Ketua Tim','Reviu Pengendali Teknis'] as $level) {
            $decision = collect($m['supervision'])->firstWhere('level', $level);
            $closing[] = self::band(mb_strtoupper($level), 3);
            $closing[] = self::row([self::cell(self::p($decision['text'] ?? 'Belum ada keputusan pada versi ini'), 10205, 3)]);
        }
        $signatures = [];
        foreach (['Pengendali Teknis','Ketua Tim','Anggota Tim'] as $i=>$role) {
            $names = [];
            foreach ($m['team'] as $member) {
                $column = in_array($member['role'], ['Ketua Tim','Pengendali Teknis'], true) ? $member['role'] : 'Anggota Tim';
                if ($column === $role) {
                    $names[] = $role === 'Anggota Tim' ? $member['role'].' · '.$member['name'] : $member['name'];
                }
            }
            $center = '<w:jc w:val="center"/>';
            $signatures[] = self::cell(self::p($role, properties: '<w:keepNext/><w:spacing w:after="480"/>'.$center)
                .self::p(implode("\n", $names), properties: $center), [3375,3485,3345][$i]);
        }
        $closing[] = self::row($signatures);
        $body .= self::table([3375,3485,3345], $closing);
        $body.='<w:sectPr><w:footerReference w:type="default" r:id="rIdFooter"/><w:pgSz w:w="11907" w:h="16839"/><w:pgMar w:top="964" w:right="851" w:bottom="851" w:left="851" w:header="709" w:footer="709" w:gutter="0"/><w:pgNumType w:start="1"/></w:sectPr>';
        $declaration='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        return [
            '[Content_Types].xml'=>$declaration.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>',
            '_rels/.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml'=>$declaration.'<w:document xmlns:w="'.self::W.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>',
            'word/_rels/document.xml.rels'=>$declaration.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/><Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/><Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>',
            'word/styles.xml'=>$declaration.'<w:styles xmlns:w="'.self::W.'"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/><w:sz w:val="22"/><w:color w:val="000000"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="160" w:after="80"/></w:pPr><w:rPr><w:b/></w:rPr></w:style></w:styles>',
            'word/numbering.xml'=>$declaration.'<w:numbering xmlns:w="'.self::W.'"><w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="singleLevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="upperLetter"/><w:lvlText w:val="%1."/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="425" w:hanging="425"/></w:pPr><w:rPr><w:b/></w:rPr></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>',
            'word/settings.xml'=>$declaration.'<w:settings xmlns:w="'.self::W.'"><w:updateFields w:val="true"/></w:settings>',
            'word/footer1.xml'=>$declaration.'<w:ftr xmlns:w="'.self::W.'"><w:p><w:pPr><w:jc w:val="right"/></w:pPr><w:fldSimple w:instr="PAGE"><w:r><w:t>1</w:t></w:r></w:fldSimple></w:p></w:ftr>',
        ];
    }

    /** Kolom kotak form: label, titik dua, isi kiri; label, titik dua, isi kanan. Jumlah 10205 = lebar area cetak. */
    private const IDENTITY=[1790,280,3380,1960,230,2565];

    private static function section(string $title,string $text): string { return self::heading($title).self::bodyText($text); }
    /** Judul bagian bernomor huruf A., B., C., … memakai penomoran Word agar urutan tetap benar saat bagian bertambah. */
    private static function heading(string $title): string
    {
        return self::p($title,'Heading1',properties: '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>');
    }
    private static function bodyText(string $text): string { return self::p($text,properties: '<w:spacing w:after="80"/><w:ind w:left="425"/><w:jc w:val="both"/>'); }
    /** Garis sel yang ditegaskan; sisi lain mengikuti garis tabel. */
    private static function edges(array $sides): string
    {
        $xml='';
        foreach (['top','left','bottom','right'] as $edge) {
            if (in_array($edge,$sides,true)) { $xml.='<w:'.$edge.' w:val="single" w:sz="4" w:color="000000"/>'; }
        }
        return '<w:tcBorders>'.$xml.'</w:tcBorders>';
    }
    private static function p(string $text,string $style='Normal',bool $bold=false, string $properties='', string $font=''): string
    {
        $runs='';
        foreach (explode("\n", $text) as $i=>$line) {
            $escaped = htmlspecialchars($line, ENT_XML1|ENT_QUOTES, 'UTF-8');
            $content = str_replace("\t", '</w:t><w:tab/><w:t xml:space="preserve">', $escaped);
            $runs .= '<w:r><w:rPr>'.$font.($bold ? '<w:b/>' : '').'</w:rPr>'
                .($i ? '<w:br/>' : '').'<w:t xml:space="preserve">'.$content.'</w:t></w:r>';
        }
        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/>'.$properties.'</w:pPr>'.$runs.'</w:p>';
    }
    /** $inner=false: hanya garis luar; garis dalam dipasang per sel bila perlu. */
    private static function table(array $widths, array $rows, bool $inner=true): string
    {
        $grid = '';
        foreach ($widths as $width) {
            $grid .= '<w:gridCol w:w="'.$width.'"/>';
        }
        $borders = '';
        foreach (['top','left','bottom','right','insideH','insideV'] as $edge) {
            $line = $inner || !str_starts_with($edge, 'inside') ? 'w:val="single" w:sz="4" w:color="000000"' : 'w:val="nil"';
            $borders .= '<w:'.$edge.' '.$line.'/>';
        }
        return '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($widths).'" w:type="dxa"/><w:tblBorders>'.$borders
            .'</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>'.implode('', $rows).'</w:tbl>';
    }

    private static function row(array $cells, bool $repeat=false): string
    {
        return '<w:tr>'.($repeat ? '<w:trPr><w:tblHeader/></w:trPr>' : '').implode('', $cells).'</w:tr>';
    }

    private static function cell(string $paragraphs, int $width, int $span=1, string $fill='', string $borders='', string $valign=''): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'
            .($span > 1 ? '<w:gridSpan w:val="'.$span.'"/>' : '')
            .$borders
            .($fill !== '' ? '<w:shd w:val="clear" w:fill="'.$fill.'"/>' : '')
            .($valign !== '' ? '<w:vAlign w:val="'.$valign.'"/>' : '')
            .'</w:tcPr>'.$paragraphs.'</w:tc>';
    }

    private static function band(string $title, int $span=1): string
    {
        return self::row([self::cell(self::p($title, bold: true, properties: '<w:keepNext/><w:jc w:val="center"/>'),
            10205, $span, 'D9D9D9', $span > 1 ? self::edges(['top','bottom']) : '')]);
    }

    private static function findingRow(array $values, array $widths, bool $header): string
    {
        $cells = [];
        foreach ($values as $i=>$value) {
            $align = ($header || $i === 0 || $i === 4) ? 'center' : ($i === 5 ? 'right' : 'left');
            $font = '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="'.($header || $i === 0 ? 16 : 22).'"/>';
            $cells[] = self::cell(self::p($value, bold: $header, properties: '<w:jc w:val="'.$align.'"/>', font: $font),
                $widths[$i], fill: $header ? 'C5E0B3' : '', valign: $header || $i < count($widths)-1 ? 'center' : 'top');
        }
        return self::row($cells, $header);
    }
}

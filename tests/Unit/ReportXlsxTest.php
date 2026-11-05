<?php

namespace Tests\Unit;

use App\Support\{ItemReport,ProposalXlsx,ReportXlsx};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportXlsxTest extends TestCase
{
    public function test_multiple_sheets_repeat_headers_and_preserve_literal_values_without_formulas_or_external_relationships(): void
    {
        $row=array_fill(0,13,'Teks simulasi');$row[5]='000123456789012345678';$row[6]='=HYPERLINK("https://example.test", "simulasi")';$row[7]="Teks <aman> & khusus\nBaris kedua";$row[10]='123.456.789.012.345.678,90';
        $rows=(function()use($row){for($i=1;$i<=5;$i++){yield array_replace($row,[0=>(string)$i]);}})();
        $path=(new ReportXlsx)->create($rows,2);$zip=new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            for($i=0;$i<$zip->numFiles;$i++){$xml=$zip->getFromIndex($i);$dom=new \DOMDocument;$this->assertTrue($dom->loadXML($xml,LIBXML_NONET));$this->assertStringNotContainsString('TargetMode="External"',$xml);}
            $doc=new \DOMDocument;$doc->loadXML($zip->getFromName('xl/workbook.xml'));$x=new \DOMXPath($doc);$x->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$this->assertSame(3,$x->query('//s:sheet')->length);
            foreach([1=>3,2=>3,3=>2] as $sheet=>$count){
                $dom=new \DOMDocument;$dom->loadXML($zip->getFromName('xl/worksheets/sheet'.$sheet.'.xml'));$xp=new \DOMXPath($dom);$xp->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $this->assertSame($count,$xp->query('//s:row')->length);$this->assertSame(0,$xp->query('//s:f')->length);$this->assertSame(0,$xp->query('//s:c[@t!="inlineStr"]')->length);
                $this->assertSame(13,$xp->query('//s:row[@r="1"]/s:c')->length);$this->assertSame('frozen',$xp->query('//s:pane')->item(0)->getAttribute('state'));
                $this->assertStringContainsString('000123456789012345678',$dom->textContent);$this->assertStringContainsString($row[6],$dom->textContent);$this->assertStringContainsString($row[10],$dom->textContent);
            }
            $zip->close();$read=(new ProposalXlsx)->read($path,2);$this->assertSame(ItemReport::HEADERS,array_column($read['rows'][1],'value'));$this->assertSame('5',$read['rows'][2][0]['value']);$this->assertFalse($read['rows'][2][6]['formula']);
        } finally {unlink($path);}
    }

    public function test_excel_escape_sequences_and_forbidden_xml_characters_are_encoded_without_loss(): void
    {
        $row=array_fill(0,13,'simulasi');$row[6]="_x000A_\x01 Ω";$path=(new ReportXlsx)->create([$row]);$zip=new \ZipArchive;
        try {$zip->open($path);$xml=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();$this->assertStringContainsString('_x005F_x000A_',$xml);$this->assertStringContainsString('_x0001_ Ω',$xml);$dom=new \DOMDocument;$this->assertTrue($dom->loadXML($xml,LIBXML_NONET));}
        finally {unlink($path);}
    }

    public function test_overlong_cells_are_rejected_rather_than_silently_truncated(): void
    {
        $row=array_fill(0,13,'simulasi');$row[6]=str_repeat('a',32768);$this->expectException(ValidationException::class);(new ReportXlsx)->create([$row]);
    }
}

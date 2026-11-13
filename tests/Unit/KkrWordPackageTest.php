<?php

namespace Tests\Unit;

use App\Support\KkrWord;
use Tests\TestCase;

class KkrWordPackageTest extends TestCase
{
    public function test_long_multiline_content_has_eight_columns_repeating_headers_page_fields_and_no_fixed_row_height(): void
    {
        $long=implode("\n",array_fill(0,18,'Catatan simulasi panjang untuk memeriksa kelanjutan tabel dan keterbacaan seluruh narasi.'));
        $m=['title'=>'KERTAS KERJA REVIU','institution'=>'Instansi simulasi','subject'=>'KKR pengujian tampilan','facts'=>[['Nomor KKR','SIMULASI'],['Versi','1'],['Nama Obrik','Instansi simulasi'],['Tahun Anggaran','2026'],['Periode','Periode simulasi'],['Penugasan','Penugasan simulasi'],['Dasar pemeriksaan','Surat tugas simulasi'],['Ruang lingkup','Usulan dalam penugasan simulasi']],
            'purpose'=>'Tujuan simulasi','work_steps'=>"Langkah pertama\nLangkah kedua",'headers'=>\App\Support\KkrPrint::HEADERS,
            'rows'=>array_map(fn($i)=>[(string)$i,'001.020','Item simulasi '.$i,'Spesifikasi simulasi','Paket','Rp 1.250,25','SKPD simulasi',$long],range(1,3)),
            'conclusion'=>'Kesimpulan simulasi','recommendation'=>'Rekomendasi simulasi','supervision'=>[],'team'=>[['role'=>'Pemeriksa','name'=>'Aktor simulasi']]];
        $bytes=KkrWord::bytes($m);$this->assertSame($bytes,KkrWord::bytes($m));
        $path=tempnam(sys_get_temp_dir(),'kkr-package-');file_put_contents($path,$bytes);$zip=new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            for($i=0;$i<$zip->numFiles;$i++){$xml=$zip->getFromIndex($i);$dom=new \DOMDocument;$this->assertTrue($dom->loadXML($xml,LIBXML_NONET));$this->assertStringNotContainsString('TargetMode="External"',$xml);}
            $document=$zip->getFromName('word/document.xml');$dom=new \DOMDocument;$dom->loadXML($document);$x=new \DOMXPath($dom);$x->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            // Identitas + tujuan + langkah + pita HASIL REVIU satu kotak menyambung (6 kolom), lalu temuan dan penutup.
            $this->assertSame(3,$x->query('//w:tbl')->length);
            $form='//w:tbl[count(w:tblGrid/w:gridCol)=6]';
            $this->assertSame(1,$x->query($form)->length);
            $this->assertSame(10205,(int)$x->evaluate('sum('.$form.'/w:tblGrid/w:gridCol/@w:w)'));
            $this->assertSame(1,$x->query($form.'/w:tr[w:tc/w:p/w:r/w:t="TUJUAN"]')->length);
            $this->assertSame(1,$x->query($form.'/w:tr[w:tc/w:p/w:r/w:t="HASIL REVIU"]')->length);
            $this->assertSame(0,$x->query($form.'/w:tblPr/w:tblBorders/w:insideH[@w:val="single"]|'.$form.'/w:tblPr/w:tblBorders/w:insideV[@w:val="single"]')->length);
            // Judul bagian bernomor huruf A.–E. lewat penomoran Word asli, bukan teks ketikan.
            $this->assertSame(5,$x->query('//w:p[w:pPr/w:numPr/w:numId/@w:val="1"]')->length);
            $this->assertStringContainsString('upperLetter',$zip->getFromName('word/numbering.xml'));
            $findings = '//w:tbl[count(w:tblGrid/w:gridCol)=8]';
            $this->assertSame(4,$x->query($findings.'/w:tr')->length);
            $this->assertSame(8,$x->query($findings.'/w:tr[1]/w:tc/w:tcPr/w:shd[@w:fill="C5E0B3"]')->length);
            $this->assertSame(5,$x->query('//w:shd[@w:fill="D9D9D9"]')->length);
            $this->assertSame(3,$x->query('(//w:tbl)[last()]/w:tr[last()]/w:tc')->length);
            $this->assertSame(1,$x->query('//w:tr[1]/w:trPr/w:tblHeader')->length);$this->assertSame(0,$x->query('//w:cantSplit|//w:trHeight|//w:noWrap')->length);
            $this->assertStringContainsString('PAGE',$zip->getFromName('word/footer1.xml'));$this->assertStringContainsString('w:updateFields',$zip->getFromName('word/settings.xml'));
            $this->assertSame(3*18,substr_count($dom->textContent,'Catatan simulasi panjang'));
            $zip->close();
            // Synthetic artifact only, for desktop Word layout QA; never production data or a public route.
            $qa=storage_path('framework/testing');if(!is_dir($qa)){mkdir($qa,0777,true);}file_put_contents($qa.'/kkr-word-qa.docx',$bytes);
        } finally {unlink($path);}
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\{AssignmentWork, InspectorateImport, InspectorateWorkbook};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MySqlAccessTestCase;

class InspectorateImportTest extends MySqlAccessTestCase
{
    private User $lead;
    private int $period;
    private int $putr;
    private int $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lead = $this->actor('team_lead');
        $admin = $this->actor('admin');
        $this->period = DB::table('submission_periods')->insertGetId(['budget_year' => 2026, 'name' => 'Periode impor simulasi', 'opens_at' => '2026-01-01', 'closes_at' => '2026-12-31', 'status' => 'open', 'created_by' => $admin->id]);
        $this->putr = DB::table('organizations')->insertGetId(['code' => 'PUTR', 'name' => 'Dinas PUTR simulasi']);
        $this->other = DB::table('organizations')->insertGetId(['code' => 'LAIN', 'name' => 'SKPD lain simulasi']);
        $this->actingAs($this->lead);
    }

    private function sheet(array $rows): string
    {
        $xml = '<worksheet><sheetData>';
        foreach ($rows as $index => $values) {
            $number = $index + 1; $xml .= '<row r="'.$number.'">';
            foreach ($values as $column => $value) {
                $ref = chr(65 + $column).$number;
                $xml .= is_array($value) ? '<c r="'.$ref.'"><v>'.htmlspecialchars($value['n'], ENT_XML1).'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    /** @param array<string,array> $sheets nama lembar => baris */
    private function workbook(array $sheets, string $name = 'usulan.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->workbookBytes($sheets));
    }

    private function workbookBytes(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'insp-'); $zip = new \ZipArchive; $zip->open($path, \ZipArchive::OVERWRITE);
        $book = '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'; $rels = '<Relationships>'; $i = 0;
        foreach ($sheets as $title => $rows) {
            $i++;
            $book .= '<sheet name="'.$title.'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$i.'.xml', $this->sheet($rows));
        }
        $zip->addFromString('xl/workbook.xml', $book.'</sheets></workbook>'); $zip->addFromString('xl/_rels/workbook.xml.rels', $rels.'</Relationships>'); $zip->close();
        $bytes = file_get_contents($path); unlink($path);

        return $bytes;
    }

    private const HEADER = ['kode', 'uraian', 'spek', 'satuan', 'harga', 'rekening', 'rekening_2', 'kelompok', 'TKDN', 'Pengusul', 'Tipe', 'Keterangan'];

    private function item(string $code, string $name, mixed $price, string $owner, string $origin = 'Data lama', string $tkdn = '45%'): array
    {
        return [$code, $name, 'Spesifikasi '.$name, 'Buah', is_numeric($price) ? ['n' => (string)$price] : $price, '5.1.02.01.01.0001', '5.1.02.01.01.0039', '1', $tkdn, $owner, $origin, ''];
    }

    private function standard(): UploadedFile
    {
        return $this->workbook($this->standardSheets());
    }

    private function standardSheets(): array
    {
        return [
            'Data_SSH' => [self::HEADER, ['1.1.12', 'Persediaan'],
                $this->item('1.1.12.01.01.0001', 'Asbes', 117400, 'PUTR'),
                $this->item('1.1.12.01.01.0002', 'Paku', 23800, 'DLH;ASB', 'Data baru', '51,88%'),
                $this->item('1.1.12.01.01.0003', 'Plat', 43000, 'ASB;PUTR;DLH'),
                $this->item('1.1.12.01.01.0004', 'Rusak', 'abc', 'PUTR'),
                $this->item('1.1.12.01.01.0005', 'Tanpa pengusul', 1000, '')],
            'Data_SBU' => [self::HEADER, $this->item('8.1.01.03.06.0001', 'Insentif', 100000, 'PUTR')],
            'Data_SSH REVIU' => [[], self::HEADER, $this->item('9.9.9', 'Tidak dibaca', 1, 'ZZZ')],
        ];
    }

    private function upload(?UploadedFile $file = null)
    {
        return $this->post(route('inspectorate.imports.upload'), ['submission_period_id' => $this->period, 'file' => $file ?? $this->standard()]);
    }

    private function useActor(User $user): void
    {
        $this->flushSession();
        $this->actingAs($user);
    }

    private function current(): object
    {
        return DB::table('inspectorate_imports')->orderByDesc('id')->first();
    }

    private function form(array $choices = [], string $intent = 'commit', array $extra = []): array
    {
        $import = $this->current(); $owners = array_keys(InspectorateImport::owners($import->id)); $keys = $values = [];
        foreach ($owners as $i => $owner) { $keys[$i] = $owner; $values[$i] = $choices[$owner] ?? 'new'; }

        return ['record_version' => InspectorateImport::token($import), 'intent' => $intent, 'owner_keys' => $keys, 'owners' => $values] + $extra;
    }

    public function test_only_inspectorate_roles_can_open_the_import_menu(): void
    {
        $this->get(route('inspectorate.imports.index'))->assertOk()->assertSee('Impor Inspektorat');
        $this->useActor($this->actor('admin'));
        $this->get(route('inspectorate.imports.index'))->assertOk();
        foreach (['skpd_operator' => $this->organization(), 'examiner' => null, 'bkad' => null, 'technical_controller' => null] as $role => $org) {
            $this->useActor($this->actor($role, $org));
            $this->get(route('inspectorate.imports.index'))->assertForbidden();
            $this->upload()->assertForbidden();
        }
        $this->assertDatabaseCount('inspectorate_imports', 0);
    }

    public function test_workbook_is_previewed_per_owner_without_creating_items(): void
    {
        $this->upload()->assertRedirect();
        $import = $this->current();
        $this->assertSame('preview', $import->status);
        // Asbes(PUTR) + Paku(DLH) + Plat(PUTR, DLH) + baris rusak + tanpa pengusul + Insentif SBU(PUTR) = 7; lembar REVIU tidak dibaca.
        $this->assertSame(7, DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->count());
        $this->assertSame(1, DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->whereNotNull('error')->count());
        $this->assertSame(0, DB::table('inspectorate_import_rows')->where('inspectorate_import_id', $import->id)->where('owner', 'ZZZ')->count());
        $this->assertDatabaseCount('proposals', 0);
        $this->assertDatabaseCount('proposal_items', 0);
        $mapping = json_decode($import->mapping, true);
        $this->assertSame(['org_id' => $this->putr], $mapping['PUTR']);
        $this->assertSame(['new' => true], $mapping['DLH']);
        $this->assertArrayNotHasKey('ASB', $mapping);
        $this->assertArrayHasKey(InspectorateWorkbook::NO_OWNER, $mapping);
        $this->get(route('inspectorate.imports.show', $import->id))->assertOk()->assertSee('Pemetaan pengusul ke SKPD')->assertSee('Harga bukan angka yang sah')->assertSee('Referensi');
    }

    public function test_commit_requires_confirmation_for_new_references_then_creates_ready_items_that_can_be_assigned(): void
    {
        $this->upload();
        $import = $this->current(); $url = route('inspectorate.imports.update', $import->id);
        $choices = ['PUTR' => 'org:'.$this->putr, 'DLH' => 'new', InspectorateWorkbook::NO_OWNER => 'org:'.$this->other];
        $this->post($url, $this->form($choices))->assertSessionHasErrors('confirm_references');
        $this->assertDatabaseCount('proposal_items', 0);
        $this->assertDatabaseCount('item_classifications', 0);

        $this->post($url, $this->form($choices, 'commit', ['confirm_references' => 1]))->assertSessionHasNoErrors()->assertRedirect(route('inspectorate.imports.show', $import->id));
        $done = $this->current();
        $this->assertSame('completed', $done->status);
        $this->assertDatabaseCount('inspectorate_import_rows', 0);
        $summary = json_decode($done->summary, true);
        $this->assertSame(1, $summary['excluded']);
        $this->assertSame(6, $summary['items']);
        $this->assertCount(4, $summary['packages']);
        $dlh = DB::table('organizations')->where('code', 'DLH')->first();
        $this->assertNotNull($dlh);

        // PUTR: Asbes + Plat (SHS) dan Insentif (SBU); DLH: Paku + Plat; tanpa pengusul dipetakan ke SKPD lain.
        $this->assertSame(2, DB::table('proposal_items as i')->join('proposals as p', 'p.id', '=', 'i.proposal_id')->where('p.organization_id', $this->putr)->where('p.type', 'SHS')->count());
        $this->assertSame(1, DB::table('proposal_items as i')->join('proposals as p', 'p.id', '=', 'i.proposal_id')->where('p.organization_id', $this->putr)->where('p.type', 'SBU')->count());
        $this->assertSame(2, DB::table('proposal_items as i')->join('proposals as p', 'p.id', '=', 'i.proposal_id')->where('p.organization_id', $dlh->id)->count());
        $this->assertSame(1, DB::table('proposal_items as i')->join('proposals as p', 'p.id', '=', 'i.proposal_id')->where('p.organization_id', $this->other)->count());
        $this->assertSame(6, DB::table('proposal_items')->where('status', 'ready')->count());
        $this->assertDatabaseHas('proposals', ['organization_id' => $this->putr, 'type' => 'SHS', 'status' => 'verification', 'submission_period_id' => $this->period]);

        $version = DB::table('proposal_item_versions')->where('description', 'Paku')->first();
        $this->assertSame('new', $version->origin);
        $this->assertSame('51.88', $version->tkdn_percent);
        $this->assertSame('23800.00', $version->proposed_price);
        $this->assertNotNull($version->submitted_at);
        $this->assertDatabaseHas('verification_results', ['item_version_id' => $version->id, 'result' => 'ready']);
        $this->assertSame(2, DB::table('item_version_accounts')->where('item_version_id', $version->id)->count());
        $this->assertSame(['5.1.02.01.01.0001', '5.1.02.01.01.0039'], array_column(json_decode($version->reference_snapshot, true)['accounts'], 'code'));
        $this->assertDatabaseHas('item_classifications', ['code' => '1.1.12.01.01.0001']);
        $this->assertDatabaseHas('units', ['name' => 'Buah']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'inspectorate_import.completed', 'actor_id' => $this->lead->id]);

        // Item langsung muncul sebagai kandidat penugasan untuk SKPD dan periode terkait; Ketua Tim tinggal menunjuk pemeriksa.
        $examiner = $this->actor('examiner');
        $this->post(route('assignments.store'), ['number' => 'IMP-TEST-1', 'title' => 'Reviu PUTR simulasi', 'submission_period_id' => $this->period,
            'object_organization_id' => $this->putr, 'examiners' => [$examiner->id]])->assertSessionHasNoErrors();
        $assignment = \App\Models\ReviewAssignment::where('number', 'IMP-TEST-1')->firstOrFail();
        $this->assertSame(3, AssignmentWork::candidates($assignment)->count());
        $this->get(route('assignments.candidates', $assignment))->assertOk()->assertSee('Asbes')->assertSee('Insentif');
        $this->get(route('inspectorate.imports.show', $done->id))->assertOk()->assertSee('Impor tersimpan')->assertSee(e(route('assignments.create', ['periode' => $this->period, 'skpd' => $this->putr])), false);
    }

    public function test_same_file_cannot_be_committed_twice_for_one_period_and_stale_forms_are_rejected(): void
    {
        $bytes = $this->workbookBytes($this->standardSheets());
        $same = fn () => UploadedFile::fake()->createWithContent('usulan.xlsx', $bytes);
        $this->upload($same());
        $first = $this->current(); $url = route('inspectorate.imports.update', $first->id);
        $stale = $this->form(['PUTR' => 'org:'.$this->putr]);
        $this->post($url, $this->form(['PUTR' => 'org:'.$this->putr, InspectorateWorkbook::NO_OWNER => 'org:'.$this->other], 'save'))->assertSessionHasNoErrors();
        $this->post($url, $stale)->assertSessionHasErrors("record_version");
        $this->assertDatabaseCount('proposal_items', 0);
        $this->post($url, $this->form(['PUTR' => 'org:'.$this->putr], 'commit', ['confirm_references' => 1]))->assertSessionHasNoErrors();
        $this->post($url, ['record_version' => str_repeat('b', 64), 'intent' => 'commit', 'owner_keys' => ['PUTR'], 'owners' => ['new'], 'confirm_references' => 1])->assertStatus(409);
        $this->upload($same())->assertSessionHasErrors('file');
        $this->assertDatabaseCount('inspectorate_imports', 1);
        $this->assertSame(6, DB::table('proposal_items')->count());
    }

    public function test_cancel_discards_preview_rows_and_blocks_commit(): void
    {
        $this->upload();
        $import = $this->current();
        $this->post(route('inspectorate.imports.cancel', $import->id))->assertRedirect(route('inspectorate.imports.index'));
        $this->assertSame('cancelled', $this->current()->status);
        $this->assertDatabaseCount('inspectorate_import_rows', 0);
        $this->post(route('inspectorate.imports.update', $import->id), ['record_version' => str_repeat('a', 64), 'intent' => 'commit', 'owner_keys' => ['PUTR'], 'owners' => ['new']])->assertStatus(409);
        $this->assertDatabaseCount('proposals', 0);
        // Pratinjau yang dibatalkan tidak mengunci berkas yang sama.
        $this->upload()->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_inactive_references_block_the_commit_and_unknown_owner_choices_are_rejected(): void
    {
        DB::table('item_classifications')->insert(['code' => '1.1.12.01.01.0001', 'name' => 'Nonaktif', 'is_active' => false]);
        $this->upload();
        $import = $this->current(); $url = route('inspectorate.imports.update', $import->id);
        $this->post($url, $this->form([], 'commit', ['confirm_references' => 1]))->assertSessionHasErrors('import');
        $this->assertDatabaseCount('proposal_items', 0);
        $this->post($url, $this->form(['PUTR' => 'org:999999'], 'save'))->assertSessionHasErrors('owners');
        $inactive = DB::table('organizations')->insertGetId(['code' => 'OFF', 'name' => 'Tidak aktif', 'is_active' => false]);
        $this->post($url, $this->form(['PUTR' => 'org:'.$inactive], 'save'))->assertSessionHasErrors('owners');
    }

    public function test_unrecognised_workbooks_are_rejected_with_a_clear_message(): void
    {
        $this->upload($this->workbook(['Lembar lain' => [self::HEADER]]))->assertSessionHasErrors('file');
        $this->upload($this->workbook(['Data_SSH' => [['kode', 'uraian', 'harga', 'Pengusul'], ['1', 'A', ['n' => '1'], 'PUTR']]]))->assertSessionHasErrors('file');
        $this->assertStringContainsString('satuan', session('errors')->first('file'));
        $this->upload($this->workbook(['Data_SSH' => [self::HEADER]]))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('inspectorate_imports', 0);
        $this->upload(UploadedFile::fake()->createWithContent('usulan.csv', "a,b\n"))->assertSessionHasErrors('file');
    }
}

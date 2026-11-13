<?php

namespace App\Http\Controllers;

use App\Support\ZiTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Template instrumen ZI: draf dapat disunting, versi terbit terkunci. */
class ZiTemplateController extends Controller
{
    private function guard(Request $r): void
    {
        abort_unless($r->user()->hasPermission('zi.template'), 403);
    }

    private function find(int $id, bool $lock = false): object
    {
        $q = DB::table('zi_instruments')->where('id', $id);
        return ($lock ? $q->lockForUpdate() : $q)->first() ?? abort(404);
    }

    private function decode(object $row): array
    {
        return [json_decode($row->items, true, flags: JSON_THROW_ON_ERROR), json_decode($row->rules, true, flags: JSON_THROW_ON_ERROR)];
    }

    private function log(int $id, string $action, ?string $detail, Request $r): void
    {
        DB::table('zi_instrument_events')->insert(['instrument_id'=>$id, 'action'=>$action, 'detail'=>$detail, 'actor_id'=>$r->user()->id, 'created_at'=>now()]);
    }

    private function draft(Request $r, object $row): void
    {
        abort_unless($row->status === 'draft', 409, 'Versi terbit terkunci. Buat draf baru dari versi ini untuk mengubahnya.');
        $r->validate(['revision'=>'required|integer|min:0']);
        if ((int)$r->revision !== (int)$row->revision) {
            throw ValidationException::withMessages(['revision'=>'Draf telah berubah. Muat ulang dan periksa versi terbaru.']);
        }
    }

    public function index(Request $r)
    {
        $this->guard($r);
        $used = DB::table('zi_assessments')->selectRaw('instrument, count(*) as total')->groupBy('instrument')->pluck('total', 'instrument');
        $list = DB::table('zi_instruments')->orderByDesc('id')->get()->map(function ($row) use ($used) {
            [$items, $rules] = $this->decode($row);
            $row->item_count = count($items);
            $row->areas = array_column($rules['areas'], 'name');
            $row->weight = array_sum(array_column($rules['areas'], 'max'));
            $row->used = $used[$row->version] ?? 0;
            return $row;
        });
        return view('zi.template.index', compact('list'));
    }

    public function store(Request $r)
    {
        $this->guard($r);
        $data = $r->validate([
            'source'=>['required', Rule::exists('zi_instruments', 'version')],
            'version'=>['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{2,39}$/', Rule::unique('zi_instruments', 'version')],
        ], ['version.regex'=>'Kode versi memakai huruf kecil, angka, dan tanda hubung (3 sampai 40 karakter).']);
        $source = DB::table('zi_instruments')->where('version', $data['source'])->first();
        $id = DB::transaction(function () use ($r, $data, $source) {
            $id = DB::table('zi_instruments')->insertGetId(['version'=>$data['version'], 'status'=>'draft', 'source'=>$source->version,
                'items'=>$source->items, 'rules'=>$source->rules, 'created_by'=>$r->user()->id, 'created_at'=>now(), 'updated_at'=>now()]);
            $this->log($id, 'created', 'Draf dibuat dari versi '.$source->version.'.', $r);
            return $id;
        });
        return to_route('zi.template.show', $id)->with('status', 'Draf dibuat. Versi sumber tidak berubah.');
    }

    public function show(Request $r, int $instrument)
    {
        $this->guard($r);
        $row = $this->find($instrument);
        [$items, $rules] = $this->decode($row);
        $issues = ZiTemplate::validate($items, $rules);
        $history = DB::table('zi_instrument_events as e')->join('users as u', 'u.id', '=', 'e.actor_id')->where('e.instrument_id', $instrument)
            ->orderByDesc('e.id')->limit(50)->get(['e.*', 'u.name as actor']);
        $used = DB::table('zi_assessments')->where('instrument', $row->version)->count();
        return view('zi.template.show', compact('row', 'items', 'rules', 'issues', 'history', 'used'));
    }

    public function item(Request $r, int $instrument, int $item)
    {
        $this->guard($r);
        $row = $this->find($instrument);
        abort_unless($row->status === 'draft', 409, 'Versi terbit terkunci.');
        [$items, $rules] = $this->decode($row);
        $question = $item === 0 ? null : ($items[$item] ?? abort(404));
        $placement = (string)$r->query('kelompok', '');
        foreach ($rules['areas'] as $area) {
            foreach ($area['groups'] as $group) {
                if ($item !== 0 && in_array($item, $group['items'], true)) {
                    $placement = $area['key'].'/'.$group['key'];
                }
            }
        }
        return view('zi.template.item', compact('row', 'items', 'rules', 'question', 'item', 'placement'));
    }

    public function update(Request $r, int $instrument)
    {
        $this->guard($r);
        $op = $r->validate(['op'=>['required', Rule::in(ZiTemplate::OPS)]])['op'];
        DB::transaction(function () use ($r, $instrument, $op) {
            $row = $this->find($instrument, true);
            $this->draft($r, $row);
            [$items, $rules] = $this->decode($row);
            $detail = ZiTemplate::apply($items, $rules, $op, $r->except(['_token', 'op', 'revision']));
            DB::table('zi_instruments')->where('id', $instrument)->update([
                'items'=>json_encode((object)$items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'rules'=>json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'revision'=>$row->revision + 1, 'updated_at'=>now()]);
            $this->log($instrument, $op, $detail, $r);
        });
        return to_route('zi.template.show', $instrument)->with('status', 'Perubahan draf tersimpan.');
    }

    public function publish(Request $r, int $instrument)
    {
        $this->guard($r);
        DB::transaction(function () use ($r, $instrument) {
            $row = $this->find($instrument, true);
            $this->draft($r, $row);
            $issues = ZiTemplate::validate(...$this->decode($row));
            if ($issues) {
                throw ValidationException::withMessages(['publish'=>$issues]);
            }
            DB::table('zi_instruments')->where('id', $instrument)->update(['status'=>'published', 'published_at'=>now(),
                'published_by'=>$r->user()->id, 'revision'=>$row->revision + 1, 'updated_at'=>now()]);
            $this->log($instrument, 'published', 'Versi diterbitkan dan dikunci.', $r);
        });
        return to_route('zi.template.show', $instrument)->with('status', 'Versi terbit. Penilaian baru dapat memakainya.');
    }

    public function destroy(Request $r, int $instrument)
    {
        $this->guard($r);
        DB::transaction(function () use ($r, $instrument) {
            $row = $this->find($instrument, true);
            $this->draft($r, $row);
            DB::table('zi_instruments')->where('id', $instrument)->delete();
        });
        return to_route('zi.template.index')->with('status', 'Draf dihapus.');
    }
}

<h2>{{ $fields['name'] }}</h2>
<p class="account-body-copy verification-copy">{{ $fields['spec'] }}</p>
<dl class="proposal-version-facts">
    @foreach(['code'=>'Kode barang','unit'=>'Kode satuan','account'=>'Rekening','price'=>'Harga'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $key==='price'?\App\Support\ProposalMoney::display($fields[$key]):$fields[$key] }}</dd></div>@endforeach
</dl>
<h3>Bukti pada versi {{ $version->version_number }}</h3>
@forelse($files as $file)<div class="proposal-file"><a href="{{ route('evidence.download',$file->id) }}">{{ $file->original_name }}</a><small>{{ \Carbon\CarbonImmutable::parse($file->created_at,'UTC')->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</small></div>@empty<p class="account-body-copy">Tidak ada bukti pada versi ini.</p>@endforelse

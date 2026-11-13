@if($nav['total']>1)<nav class="review-nav" aria-label="Pindah item reviu">
@if($nav['prev'])<a class="btn secondary" rel="prev" href="{{ route('reviews.desk',[$nav['assignment'],$nav['prev']]) }}">&lsaquo; Sebelumnya</a>@else<span class="btn secondary" aria-disabled="true">&lsaquo; Sebelumnya</span>@endif
@if($nav['position'])<span class="review-nav-position">Item {{ $nav['position'] }} dari {{ $nav['total'] }}</span>@endif
@if($nav['next'])<a class="btn secondary" rel="next" href="{{ route('reviews.desk',[$nav['assignment'],$nav['next']]) }}">Selanjutnya &rsaquo;</a>@else<span class="btn secondary" aria-disabled="true">Selanjutnya &rsaquo;</span>@endif
</nav>@endif

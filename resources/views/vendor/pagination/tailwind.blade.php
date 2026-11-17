{{--
    Pagination (F3 §1, H.1.6, H.2.4): kiri "Menampilkan {first}–{last} dari {total}", kanan tombol bulat.
    Halaman aktif berlatar arang. Teks Sebelumnya/Berikutnya dari lang/id/pagination.php. Jangkar test: data-pagination.
--}}
@if ($paginator->hasPages())
    @php
        $kelasTombol = 'inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-medium';
        $kelasAktif = $kelasTombol.' text-stone-700 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30';
        $kelasMati = $kelasTombol.' text-stone-300 cursor-default';
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" data-pagination class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-500 tabular-nums">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $kelasMati }}" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $kelasAktif }}">{!! __('pagination.previous') !!}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $kelasMati }}" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="{{ $kelasTombol }} bg-arang text-white tabular-nums">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $kelasAktif }} tabular-nums" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $kelasAktif }}">{!! __('pagination.next') !!}</a>
            @else
                <span class="{{ $kelasMati }}" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif

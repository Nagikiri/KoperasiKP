{{--
    Pagination (F3 §1, F3b F / H.3.4): kiri "Menampilkan {first}–{last} dari {total}", kanan ‹ 1 2 3 … 18 ›.
    Halaman ditampilkan: pertama, terakhir, aktif ±1; celah satu halaman ditulis angkanya, celah ≥ 2 halaman ditulis "…".
    Dihitung sendiri dari currentPage()/lastPage() (tanpa $elements bawaan). Jangkar test: data-pagination.
--}}
@if ($paginator->hasPages())
    @php
        $saatIni = $paginator->currentPage();
        $akhir = $paginator->lastPage();

        $inti = collect([1, $saatIni - 1, $saatIni, $saatIni + 1, $akhir])
            ->filter(fn ($halaman) => $halaman >= 1 && $halaman <= $akhir)
            ->unique()
            ->sort()
            ->values();

        $daftarHalaman = [];
        $sebelumnya = null;
        foreach ($inti as $halaman) {
            if ($sebelumnya !== null && $halaman - $sebelumnya === 2) {
                $daftarHalaman[] = $sebelumnya + 1;
            } elseif ($sebelumnya !== null && $halaman - $sebelumnya > 2) {
                $daftarHalaman[] = '…';
            }
            $daftarHalaman[] = $halaman;
            $sebelumnya = $halaman;
        }

        $kelasTombol = 'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-sm font-medium tabular-nums';
        $kelasTautan = $kelasTombol.' text-stone-600 transition-colors hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30';
        $kelasPanah = 'inline-flex h-9 w-9 items-center justify-center rounded-md';
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" data-pagination class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-500 tabular-nums">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $kelasPanah }} text-stone-300" aria-disabled="true" aria-label="Halaman sebelumnya">
                    <x-ui.icon name="chevron-left" class="h-4 w-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya"
                   class="{{ $kelasPanah }} text-stone-600 transition-colors hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">
                    <x-ui.icon name="chevron-left" class="h-4 w-4" />
                </a>
            @endif

            @foreach ($daftarHalaman as $halaman)
                @if ($halaman === '…')
                    <span class="{{ $kelasTombol }} text-stone-400" aria-hidden="true" data-celah>…</span>
                @elseif ($halaman === $saatIni)
                    <span aria-current="page" class="{{ $kelasTombol }} bg-arang text-white">{{ $halaman }}</span>
                @else
                    <a href="{{ $paginator->url($halaman) }}" class="{{ $kelasTautan }}" aria-label="Halaman {{ $halaman }}">{{ $halaman }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya"
                   class="{{ $kelasPanah }} text-stone-600 transition-colors hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">
                    <x-ui.icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span class="{{ $kelasPanah }} text-stone-300" aria-disabled="true" aria-label="Halaman berikutnya">
                    <x-ui.icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </div>
    </nav>
@endif

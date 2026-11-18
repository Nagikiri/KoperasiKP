{{--
    Pagination sederhana (F3b H.3.5 / H.5.1): saat ini tidak dipakai di mana pun (tidak ada simplePaginate).
    Dirapikan menjadi panah ‹ › dengan gaya yang sama dengan tailwind.blade.php, tanpa angka halaman dan tanpa teks total.
--}}
@if ($paginator->hasPages())
    @php
        $kelasPanah = 'inline-flex h-9 w-9 items-center justify-center rounded-full';
        $kelasAktif = $kelasPanah.' text-stone-600 transition-colors hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30';
    @endphp
    <nav role="navigation" aria-label="Navigasi halaman" data-pagination class="flex items-center justify-end gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $kelasPanah }} text-stone-300" aria-disabled="true" aria-label="Halaman sebelumnya">
                <x-ui.icon name="chevron-left" class="h-4 w-4" />
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya" class="{{ $kelasAktif }}">
                <x-ui.icon name="chevron-left" class="h-4 w-4" />
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya" class="{{ $kelasAktif }}">
                <x-ui.icon name="chevron-right" class="h-4 w-4" />
            </a>
        @else
            <span class="{{ $kelasPanah }} text-stone-300" aria-disabled="true" aria-label="Halaman berikutnya">
                <x-ui.icon name="chevron-right" class="h-4 w-4" />
            </span>
        @endif
    </nav>
@endif

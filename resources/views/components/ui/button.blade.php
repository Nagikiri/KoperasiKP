{{--
    Tombol (F1 §3.2, F2b C.1). variant: primary (arang)|brand (merah, hanya tombol Masuk di login)|secondary|ghost|danger;
    size: md|sm; href → <a>, selain itu <button>. square (F3 H.3.3): tombol ikon persegi (w-8/w-10, tanpa padding).
--}}
@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'icon' => null, 'type' => 'submit', 'square' => false])
@php
    $varian = [
        'primary' => 'bg-arang text-white hover:bg-arang-700',
        'brand' => 'bg-merah text-white hover:bg-merah-700',
        'secondary' => 'bg-white border border-stone-300 text-stone-800 hover:bg-stone-50',
        'ghost' => 'text-stone-700 hover:bg-stone-100',
        'danger' => 'bg-white border border-red-200 text-red-700 hover:bg-red-50',
    ][$variant] ?? '';
    $ukuran = $size === 'sm'
        ? ($square ? 'h-8 w-8 px-0 text-xs' : 'h-8 px-3 text-xs gap-1.5')
        : ($square ? 'h-10 w-10 px-0 text-sm' : 'h-10 px-4 text-sm gap-2');
    $kelas = 'inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed '.$ukuran.' '.$varian;
    $kelasIkon = $size === 'sm' ? 'w-4 h-4' : 'w-5 h-5';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$kelasIkon" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $kelas]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$kelasIkon" />@endif
        {{ $slot }}
    </button>
@endif

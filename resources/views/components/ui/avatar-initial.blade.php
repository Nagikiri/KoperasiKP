{{--
    Lingkaran berisi inisial nama, maksimal 2 huruf (F1 §3.11).
    tone: light (stone-100/stone-700, default) | dark (arang/emas, tombol akun). size: sm (28 px, F3b H.2.4) | md (36 px) | lg (64 px).
--}}
@props(['nama', 'tone' => 'light', 'size' => 'md'])
@php
    $kata = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY);
    $inisial = mb_strtoupper(implode('', array_map(fn ($k) => mb_substr($k, 0, 1), array_slice($kata, 0, 2))));
    $warna = $tone === 'dark' ? 'bg-arang text-emas' : 'bg-stone-100 text-stone-700';
    $ukuran = match ($size) {
        'lg' => 'w-16 h-16 text-xl',
        'sm' => 'w-7 h-7 text-[11px]',
        default => 'w-9 h-9 text-xs',
    };
@endphp
<span aria-hidden="true" {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold '.$ukuran.' '.$warna]) }}>{{ $inisial }}</span>

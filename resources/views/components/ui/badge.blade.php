{{-- Badge pil kecil (F1 §3.3). tone: success|danger|warning|info|neutral|brand. --}}
@props(['tone' => 'neutral'])
@php
    $warna = [
        'success' => 'bg-emerald-50 text-emerald-700',
        'danger' => 'bg-red-50 text-red-700',
        'warning' => 'bg-amber-50 text-amber-700',
        'info' => 'bg-sky-50 text-sky-700',
        'neutral' => 'bg-stone-100 text-stone-700',
        'brand' => 'bg-emas-50 text-emas-700',
    ][$tone] ?? 'bg-stone-100 text-stone-700';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center text-xs font-medium px-2 py-0.5 rounded '.$warna]) }}>{{ $slot }}</span>

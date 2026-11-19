{{--
    Kartu ringkasan (F1 §3.10, revisi F2c B/K): judul, angka besar, garis putus-putus, lalu baris rincian.
    rincian: array baris ['label' => string, 'nilai' => string, 'warna' => kelas teks opsional].
    size (F5 H.2.1): md (default, seperti sebelumnya) | sm (padding lebih kecil, angka text-xl).
    titik (F5 H.2.2): opsional, kelas latar titik 6 px di depan label (mis. bg-setor / bg-tarik).
--}}
@props(['label', 'value', 'rincian' => [], 'size' => 'md', 'titik' => null])
@php
    $kecil = $size === 'sm';
    $kelasLabel = $titik ? 'flex items-center gap-2 text-sm text-stone-600' : 'text-sm text-stone-600';
@endphp
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl '.($kecil ? 'px-5 py-4' : 'p-5').' shadow-[0_1px_2px_rgba(28,25,23,0.04),0_2px_6px_rgba(28,25,23,0.04)]']) }}>
    <p class="{{ $kelasLabel }}">@if ($titik)<span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $titik }}" data-titik-stat></span>@endif{{ $label }}</p>
    <p class="mt-1 whitespace-nowrap {{ $kecil ? 'text-xl' : 'text-[28px]' }} font-semibold leading-tight tracking-tight text-stone-900 tabular-nums">{{ $value }}</p>
    @if (count($rincian))
        <div class="my-4 border-t border-dashed border-stone-200" data-garis-putus></div>
        <dl class="space-y-2 text-sm">
            @foreach ($rincian as $baris)
                <div class="flex items-center justify-between gap-3" data-rincian>
                    <dt class="text-stone-500">{{ $baris['label'] }}</dt>
                    <dd class="font-semibold tabular-nums {{ $baris['warna'] ?? 'text-stone-900' }}">{{ $baris['nilai'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>

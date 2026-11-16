{{--
    Kartu ringkasan (F1 §3.10, F2b C.4, F2c B): label kecil, angka besar, baris info di slot.
    tone: terang (default) | gelap (latar arang, teks putih; kartu Total F2c B.1).
    aksen: null | arang | setor | tarik — garis 3 px di sisi kiri kartu (F2c B.2–B.4, H).
    icon/iconTone: ikon opsional di kanan atas (F2b); tidak dipakai lagi di dashboard sejak F2c.
--}}
@props(['label', 'value', 'icon' => null, 'iconTone' => 'neutral', 'tone' => 'terang', 'aksen' => null])
@php
    $gelap = $tone === 'gelap';
    $warnaIkon = [
        'neutral' => 'bg-stone-100 text-stone-600',
        'setor' => 'bg-setor-50 text-setor',
        'tarik' => 'bg-tarik-50 text-tarik',
    ][$iconTone] ?? 'bg-stone-100 text-stone-600';
    $warnaAksen = ['arang' => 'bg-arang', 'setor' => 'bg-setor', 'tarik' => 'bg-tarik'][$aksen] ?? null;
    $permukaan = $gelap
        ? 'bg-arang border-arang text-white'
        : 'bg-white border-stone-200 shadow-[0_1px_2px_rgba(28,25,23,0.04),0_2px_6px_rgba(28,25,23,0.04)]';
@endphp
<div {{ $attributes->merge(['class' => 'relative overflow-hidden border rounded-2xl p-6 '.$permukaan]) }} @if ($aksen) data-aksen="{{ $aksen }}" @endif>
    @if ($warnaAksen)
        <span class="absolute inset-y-0 left-0 w-[3px] {{ $warnaAksen }}" aria-hidden="true"></span>
    @endif
    <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] font-medium {{ $gelap ? 'text-arang-400' : 'text-stone-500' }}">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $warnaIkon }}">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </span>
        @endif
    </div>
    <p class="mt-2 whitespace-nowrap text-[30px] font-semibold leading-tight tracking-tight tabular-nums {{ $gelap ? 'text-white' : 'text-stone-900' }}">{{ $value }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-3 text-xs {{ $gelap ? 'text-arang-400' : 'text-stone-500' }}">{{ $slot }}</div>
    @endif
</div>

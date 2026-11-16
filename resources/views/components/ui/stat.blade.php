{{--
    Kartu ringkasan (F1 §3.10, F2b C.4): label kecil, angka besar, keterangan opsional di slot.
    icon: nama ikon x-ui.icon (opsional, kanan atas); iconTone: neutral|setor|tarik.
--}}
@props(['label', 'value', 'icon' => null, 'iconTone' => 'neutral'])
@php
    $warnaIkon = [
        'neutral' => 'bg-stone-100 text-stone-600',
        'setor' => 'bg-setor-50 text-setor',
        'tarik' => 'bg-tarik-50 text-tarik',
    ][$iconTone] ?? 'bg-stone-100 text-stone-600';
@endphp
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl p-6']) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm text-stone-500">{{ $label }}</p>
        @if ($icon)
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $warnaIkon }}">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </span>
        @endif
    </div>
    <p class="{{ $icon ? 'mt-1' : 'mt-2' }} text-[28px] font-semibold leading-tight text-stone-900 tabular-nums">{{ $value }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-2 text-sm text-stone-500">{{ $slot }}</div>
    @endif
</div>

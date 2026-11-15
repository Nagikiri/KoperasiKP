{{-- Kartu ringkasan (F1 §3.10): label kecil, angka besar, keterangan opsional di slot. --}}
@props(['label', 'value'])
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl p-6']) }}>
    <p class="text-xs font-medium text-stone-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold text-stone-900 tabular-nums">{{ $value }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-2 text-xs text-stone-500">{{ $slot }}</div>
    @endif
</div>

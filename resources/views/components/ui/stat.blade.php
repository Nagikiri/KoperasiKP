{{--
    Kartu ringkasan (F1 §3.10, revisi F2c B/K): judul, angka besar, garis putus-putus, lalu baris rincian.
    rincian: array baris ['label' => string, 'nilai' => string, 'warna' => kelas teks opsional].
--}}
@props(['label', 'value', 'rincian' => []])
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl p-5 shadow-[0_1px_2px_rgba(28,25,23,0.04),0_2px_6px_rgba(28,25,23,0.04)]']) }}>
    <p class="text-sm text-stone-600">{{ $label }}</p>
    <p class="mt-1 whitespace-nowrap text-[28px] font-semibold leading-tight tracking-tight text-stone-900 tabular-nums">{{ $value }}</p>
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

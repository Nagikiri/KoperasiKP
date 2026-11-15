{{-- Kartu putih (F1 §3.1). padding: kelas Tailwind (default p-6) atau "none" untuk tabel menempel tepi. --}}
@props(['padding' => 'p-6'])
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl shadow-[0_1px_2px_rgba(0,0,0,0.03)]']) }}>
    @isset($header)
        <div {{ $header->attributes->merge(['class' => 'flex items-center justify-between gap-4 border-b border-stone-200 px-6 py-4']) }}>
            {{ $header }}
        </div>
    @endisset
    <div class="{{ $padding === 'none' ? '' : $padding }}">
        {{ $slot }}
    </div>
</div>

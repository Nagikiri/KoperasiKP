{{--
    Kartu putih (F1 §3.1). padding: kelas Tailwind (default p-6) atau "none" untuk tabel menempel tepi.
    headerBorder (F2b C.4): false = slot header tanpa garis bawah. Bayangan tipis (F2c A.2).
--}}
@props(['padding' => 'p-6', 'headerBorder' => true])
<div {{ $attributes->merge(['class' => 'bg-white border border-stone-200 rounded-2xl shadow-[0_1px_2px_rgba(28,25,23,0.04),0_2px_6px_rgba(28,25,23,0.04)]']) }}>
    @isset($header)
        <div {{ $header->attributes->merge(['class' => 'flex items-center justify-between gap-4 px-6 '.($headerBorder ? 'border-b border-stone-200 py-4' : 'pt-6 pb-2')]) }}>
            {{ $header }}
        </div>
    @endisset
    <div class="{{ $padding === 'none' ? '' : $padding }}">
        {{ $slot }}
    </div>
</div>

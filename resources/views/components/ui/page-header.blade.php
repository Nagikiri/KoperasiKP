{{-- Judul halaman (F1 §3.6). back: URL opsional untuk tautan kembali; slot actions di kanan. --}}
@props(['title', 'subtitle' => null, 'back' => null])
<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    @if ($back)
        <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-sm text-stone-500 hover:text-stone-800">
            <x-ui.icon name="arrow-left" class="w-4 h-4" />
            Kembali
        </a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[26px] font-semibold tracking-tight text-stone-900">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-stone-600">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>

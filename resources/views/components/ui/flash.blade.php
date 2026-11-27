{{-- Flash (F1 §3.5): hanya session('success') dan session('info'). Error validasi tidak tampil di sini. --}}
@php
    $daftar = array_filter([
        'success' => session('success'),
        'info' => session('info'),
    ]);
    $gaya = [
        'success' => ['kotak' => 'bg-emerald-50 border-emerald-200 text-emerald-700', 'ikon' => 'check-circle'],
        'info' => ['kotak' => 'bg-sky-50 border-sky-200 text-sky-700', 'ikon' => 'information-circle'],
    ];
@endphp
@if ($daftar)
    <div {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @foreach ($daftar as $tone => $pesan)
            <div data-flash="{{ $tone }}" x-data="{ buka: true }" x-show="buka" role="status"
                 class="flex items-start gap-3 rounded-md border px-4 py-3 text-sm {{ $gaya[$tone]['kotak'] }}">
                <x-ui.icon :name="$gaya[$tone]['ikon']" class="w-5 h-5 shrink-0" />
                <p class="flex-1">{{ $pesan }}</p>
                <button type="button" x-on:click="buka = false" aria-label="Tutup pesan"
                        class="shrink-0 rounded-md p-0.5 opacity-70 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-merah">
                    <x-ui.icon name="x-mark" class="w-4 h-4" />
                </button>
            </div>
        @endforeach
    </div>
@endif

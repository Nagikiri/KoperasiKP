{{--
    Sidebar admin (F1 §4.1): hanya navigasi fitur, grup menu selalu terbuka (tanpa dropdown).
    Menu akun (Pengaturan Akun, Keluar) ada di topbar.
    Jangkar test: data-sidebar; data-menu-grup + data-menu-aktif pada pembungkus grup; aria-current="page" setelah href.
--}}
@php($adaLogo = file_exists(public_path('images/logo-koperasi.png')))
<aside data-sidebar class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-arang text-stone-300">
    <div class="flex items-center gap-3 px-5 py-5">
        @if ($adaLogo)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white p-1">
                <img src="{{ asset('images/logo-koperasi.png') }}" alt="Lambang Koperasi Indonesia" class="h-full w-full object-contain">
            </span>
        @else
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-merah text-xs font-semibold text-white">KDU</span>
        @endif
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">KSP Karya Duta Utama</p>
            <p class="truncate text-xs text-arang-400">Sistem Informasi Simpanan</p>
        </div>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        @foreach ($menu as $grup)
            @php($grupAktif = collect($grup['item'])->contains(fn ($item) => request()->routeIs($item['aktif'])))
            <div @if ($grup['grup']) data-menu-grup="{{ $grup['grup'] }}" data-menu-aktif="{{ $grupAktif ? '1' : '0' }}" @endif>
                @if ($grup['grup'])
                    <p class="mb-2 px-3 text-xs font-medium uppercase tracking-wide text-arang-400">{{ $grup['grup'] }}</p>
                @endif
                <ul class="space-y-1">
                    @foreach ($grup['item'] as $item)
                        @php($aktif = request()->routeIs($item['aktif']))
                        <li>
                            <a href="{{ route($item['route']) }}"@if ($aktif) aria-current="page"@endif
                               class="flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition-colors {{ $aktif ? 'bg-arang-700 text-white' : 'text-stone-300 hover:bg-arang-700 hover:text-white' }}">
                                <x-ui.icon :name="$item['ikon']" class="h-5 w-5 shrink-0 {{ $aktif ? 'text-emas' : 'text-arang-400' }}" />
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>

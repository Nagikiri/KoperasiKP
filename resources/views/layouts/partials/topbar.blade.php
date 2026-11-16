{{--
    Topbar (F1 §4.2, F2b C.2): jejak di kiri hanya untuk halaman turunan (kosong untuk halaman setingkat menu);
    tanggal hari ini dan menu akun di kanan. Tanpa pencarian dan notifikasi.
    Menu akun: dropdown Alpine (Enter/Space membuka lewat <button>, Esc dan klik di luar menutup).
    Jangkar test: data-topbar, data-jejak, data-menu-akun.
--}}
@php
    $user = Auth::user();
    $aktifAkun = request()->routeIs('password.edit');
@endphp
<header data-topbar class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-stone-200 bg-white px-8">
    <div data-jejak class="min-w-0 truncate text-sm text-stone-500">
        @if ($breadcrumb)
            {{ $breadcrumb }}
        @elseif ($jejak)
            @foreach ($jejak as $bagian)
                @if (! $loop->first)<span class="mx-1.5 text-stone-300">/</span>@endif
                @if (isset($bagian['url']))
                    <a href="{{ $bagian['url'] }}" class="hover:text-stone-900 hover:underline">{{ $bagian['label'] }}</a>
                @else
                    <span class="{{ $loop->last ? 'font-medium text-stone-900' : '' }}">{{ $bagian['label'] }}</span>
                @endif
            @endforeach
        @endif
    </div>

    <div class="flex shrink-0 items-center gap-4">
        <p class="text-sm text-stone-500">{{ \App\Support\Format::hariTanggal(now()) }}</p>

        <span class="h-6 w-px bg-stone-200" aria-hidden="true"></span>

        <div data-menu-akun class="relative" x-data="{ buka: false }"
             x-on:keydown.escape.window="if (buka) { buka = false; $refs.tombol.focus() }"
             x-on:click.outside="buka = false">
            <button type="button" x-ref="tombol" x-on:click="buka = ! buka"
                    aria-haspopup="menu" aria-controls="menu-akun" x-bind:aria-expanded="buka.toString()" aria-expanded="false"
                    class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-merah focus-visible:ring-offset-2">
                <x-ui.avatar-initial :nama="$user->nama" tone="dark" />
                <span class="text-sm font-medium text-stone-900">{{ $user->nama }}</span>
                <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
            </button>

            <div id="menu-akun" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                 class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                <div class="px-4 py-3">
                    <p class="truncate text-sm font-medium text-stone-900">{{ $user->nama }}</p>
                    <p class="truncate text-xs text-stone-500">{{ $user->username }}</p>
                </div>
                <div class="border-t border-stone-100 py-1">
                    <a href="{{ route('password.edit') }}"@if ($aktifAkun) aria-current="page"@endif
                       class="flex items-center gap-3 px-4 py-2 text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none {{ $aktifAkun ? 'font-medium text-stone-900' : '' }}">
                        <x-ui.icon name="cog-6-tooth" class="h-5 w-5 text-stone-500" />
                        Pengaturan Akun
                    </a>
                </div>
                <div class="border-t border-stone-100 py-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                            <x-ui.icon name="arrow-right-on-rectangle" class="h-5 w-5" />
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

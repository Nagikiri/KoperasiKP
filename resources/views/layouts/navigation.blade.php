@php
    // Menu ringkas (revisi R1): grup Transaksi dan Laporan memakai komponen dropdown Breeze.
    $menuTransaksi = [
        ['route' => 'transaksi.setor.create', 'aktif' => 'transaksi.setor.*', 'label' => 'Setor'],
        ['route' => 'transaksi.tarik.create', 'aktif' => 'transaksi.tarik.*', 'label' => 'Tarik'],
        ['route' => 'transaksi.riwayat', 'aktif' => 'transaksi.riwayat', 'label' => 'Riwayat Transaksi'],
    ];
    $menuLaporan = [
        ['route' => 'laporan.keseluruhan', 'aktif' => 'laporan.keseluruhan*', 'label' => 'Rekap Keseluruhan'],
        ['route' => 'laporan.anggota', 'aktif' => 'laporan.anggota*', 'label' => 'Laporan per Anggota'],
    ];
    $grupMenu = [
        ['label' => 'Transaksi', 'aktif' => request()->routeIs('transaksi.*'), 'item' => $menuTransaksi],
        ['label' => 'Laporan', 'aktif' => request()->routeIs('laporan.*'), 'item' => $menuLaporan],
    ];

    $kelasPemicu = 'inline-flex items-center px-1 pt-1 h-16 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out';
    $kelasPemicuAktif = 'border-indigo-400 text-gray-900';
    $kelasPemicuPasif = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                    <x-nav-link :href="route('anggota.index')" :active="request()->routeIs('anggota.*')">
                        Data Anggota
                    </x-nav-link>

                    @foreach ($grupMenu as $grup)
                        <div class="flex items-center" data-menu-grup="{{ $grup['label'] }}" data-menu-aktif="{{ $grup['aktif'] ? '1' : '0' }}">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button type="button" class="{{ $kelasPemicu }} {{ $grup['aktif'] ? $kelasPemicuAktif : $kelasPemicuPasif }}">
                                        <div>{{ $grup['label'] }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    @foreach ($grup['item'] as $item)
                                        @php($itemAktif = request()->routeIs($item['aktif']))
                                        <x-dropdown-link :href="route($item['route'])"
                                                         :class="$itemAktif ? 'font-semibold bg-gray-100' : ''"
                                                         :aria-current="$itemAktif ? 'page' : null">
                                            {{ $item['label'] }}
                                        </x-dropdown-link>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->nama }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('password.edit')">
                            Ganti Password
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                Keluar
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('anggota.index')" :active="request()->routeIs('anggota.*')">
                Data Anggota
            </x-responsive-nav-link>

            @foreach ($grupMenu as $grup)
                <div class="pt-2 px-4 text-xs font-semibold uppercase tracking-widest {{ $grup['aktif'] ? 'text-indigo-700' : 'text-gray-400' }}">
                    {{ $grup['label'] }}
                </div>
                @foreach ($grup['item'] as $item)
                    <x-responsive-nav-link :href="route($item['route'])" :active="request()->routeIs($item['aktif'])">
                        {{ $item['label'] }}
                    </x-responsive-nav-link>
                @endforeach
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->nama }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->username }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('password.edit')">
                    Ganti Password
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        Keluar
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

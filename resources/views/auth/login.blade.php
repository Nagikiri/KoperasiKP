{{--
    Halaman login (F1 §5): kartu dua kolom, form di kiri, panel gambar di kanan (disembunyikan di bawah md).
    Panel memakai public/images/login-bg.jpg; bila tidak ada, latar arang dengan pola titik halus.
--}}
@php
    $adaLogo = file_exists(public_path('images/logo-koperasi.png'));
    $adaFoto = file_exists(public_path('images/login-bg.jpg'));
    $polaTitik = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18'%3E%3Ccircle cx='2' cy='2' r='1' fill='%23ffffff' fill-opacity='0.12'/%3E%3C/svg%3E\")";
@endphp
<x-guest-layout>
    <div class="grid min-h-[560px] overflow-hidden rounded-3xl bg-white shadow-xl md:grid-cols-2">
        {{-- Kolom kiri: form, footer menempel ke bawah kartu. --}}
        <div class="flex flex-col px-8 pb-6 pt-10 sm:px-12">
            <div class="flex flex-1 flex-col justify-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full border border-stone-200 bg-white p-1.5">
                    @if ($adaLogo)
                        <img src="{{ asset('images/logo-koperasi.png') }}" alt="Lambang Koperasi Indonesia" class="h-full w-full object-contain">
                    @else
                        <span class="flex h-full w-full items-center justify-center rounded-full bg-merah text-[10px] font-semibold text-white">KDU</span>
                    @endif
                </span>

                <span class="mt-6 block h-[3px] w-8 rounded-full bg-emas" aria-hidden="true"></span>
                <p class="mt-3 text-xs uppercase tracking-wide text-stone-500">Selamat datang di</p>
                <h1 class="mt-1 text-2xl font-semibold text-stone-900">KSP Karya Duta Utama</h1>
                <p class="mt-1 text-sm text-stone-600">Masuk dengan akun staff koperasi.</p>

                <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
                    @csrf

                    <div>
                        <x-ui.label for="username" value="Username" class="sr-only" />
                        <div class="relative">
                            <x-ui.icon name="user" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" />
                            <x-ui.input id="username" type="text" name="username" :value="old('username')" placeholder="Username" required autofocus autocomplete="username" pil ikon-kiri />
                        </div>
                        <x-ui.field-error name="username" class="px-5" />
                    </div>

                    <div x-data="{ lihat: false }">
                        <x-ui.label for="password" value="Kata Sandi" class="sr-only" />
                        <div class="relative">
                            <x-ui.icon name="lock-closed" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" />
                            <x-ui.input id="password" type="password" name="password" placeholder="Kata sandi" required autocomplete="current-password" x-bind:type="lihat ? 'text' : 'password'" pil ikon-kiri ruang-kanan />
                            <button type="button" data-lihat-kata-sandi x-on:click="lihat = ! lihat"
                                    aria-label="Tampilkan kata sandi" x-bind:aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                    class="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100 hover:text-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-merah">
                                <x-ui.icon name="eye" class="h-5 w-5" x-show="! lihat" />
                                <x-ui.icon name="eye-slash" class="h-5 w-5" x-show="lihat" x-cloak />
                            </button>
                        </div>
                        <x-ui.field-error name="password" class="px-5" />
                    </div>

                    <label for="remember_me" class="inline-flex items-center gap-2 px-1">
                        <input id="remember_me" type="checkbox" name="remember" class="rounded border-stone-300 text-merah focus:ring-merah">
                        <span class="text-sm text-stone-600">Ingat saya</span>
                    </label>

                    <x-ui.button variant="primary" class="h-12 w-full">Masuk</x-ui.button>

                    <p class="text-center text-xs text-stone-500">Lupa kata sandi? Hubungi pengurus koperasi.</p>
                </form>
            </div>

            <p data-footer-login class="pt-8 text-center text-xs text-stone-400">Khusus pengurus KSP Karya Duta Utama &middot; &copy; {{ now()->year }}</p>
        </div>

        {{-- Kolom kanan: panel gambar. --}}
        <div data-panel-latar="{{ $adaFoto ? 'foto' : 'pola' }}"
             class="relative hidden flex-col overflow-hidden bg-arang md:flex"
             @unless ($adaFoto) style="background-image: {{ $polaTitik }};" @endunless>
            @if ($adaFoto)
                <img src="{{ asset('images/login-bg.jpg') }}" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-0 bg-arang/75" aria-hidden="true"></div>
            @endif

            <div class="relative flex flex-1 flex-col items-center justify-center px-10 text-center">
                <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white p-3">
                    @if ($adaLogo)
                        <img src="{{ asset('images/logo-koperasi.png') }}" alt="Lambang Koperasi Indonesia" class="h-full w-full object-contain">
                    @else
                        <span class="flex h-full w-full items-center justify-center rounded-full bg-merah text-xl font-semibold text-white">KDU</span>
                    @endif
                </span>
                <p class="mt-6 text-2xl font-semibold text-white">KSP Karya Duta Utama</p>
                <p class="mt-2 text-sm text-stone-300">Bersama membangun kesejahteraan anggota.</p>
            </div>

            <div class="relative" aria-hidden="true">
                <div class="h-1 bg-merah"></div>
                <div class="h-1 bg-emas"></div>
            </div>
        </div>
    </div>
</x-guest-layout>

{{-- Pengaturan Akun (F1 §6): profil hanya-baca + form ganti password. Tidak ada edit profil (di luar scope). --}}
@php($user = auth()->user())
<x-app-layout>
    <x-slot name="breadcrumb">
        <span class="font-medium text-stone-900">Pengaturan Akun</span>
    </x-slot>

    <x-ui.page-header title="Pengaturan Akun" subtitle="Profil dan keamanan akun staff." />

    <div class="grid items-start gap-6 lg:grid-cols-5">
        <x-ui.card class="lg:col-span-2" data-kartu-profil>
            <x-slot name="header">
                <h2 class="text-base font-semibold text-stone-900">Profil</h2>
            </x-slot>

            <div class="flex items-center gap-4">
                <x-ui.avatar-initial :nama="$user->nama" tone="dark" size="lg" />
                <div class="min-w-0">
                    <p class="truncate text-base font-semibold text-stone-900">{{ $user->nama }}</p>
                    <p class="truncate text-sm text-stone-500">{{ $user->username }}</p>
                </div>
            </div>

            <dl class="mt-6 divide-y divide-stone-100 border-t border-stone-100 text-sm">
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-stone-500">Nama</dt>
                    <dd class="text-right font-medium text-stone-900">{{ $user->nama }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-stone-500">Username</dt>
                    <dd class="text-right font-medium text-stone-900">{{ $user->username }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-stone-500">No. telepon</dt>
                    <dd class="text-right font-medium text-stone-900">{{ filled($user->no_telepon) ? $user->no_telepon : '–' }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-xs text-stone-500">Perubahan data profil dilakukan oleh pengelola sistem.</p>
        </x-ui.card>

        <x-ui.card class="lg:col-span-3" data-kartu-ganti-password>
            <x-slot name="header">
                <h2 class="text-base font-semibold text-stone-900">Ganti Password</h2>
            </x-slot>

            <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-ui.label for="current_password" value="Kata sandi saat ini" />
                    <x-ui.input id="current_password" class="mt-1.5" type="password" name="current_password" autocomplete="current-password" bag="updatePassword" />
                    <x-ui.field-error name="current_password" bag="updatePassword" />
                </div>

                <div>
                    <x-ui.label for="password" value="Kata sandi baru" />
                    <x-ui.input id="password" class="mt-1.5" type="password" name="password" autocomplete="new-password" bag="updatePassword" />
                    <p class="mt-1.5 text-xs text-stone-500">Minimal 8 karakter.</p>
                    <x-ui.field-error name="password" bag="updatePassword" />
                </div>

                <div>
                    <x-ui.label for="password_confirmation" value="Ulangi kata sandi baru" />
                    <x-ui.input id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" autocomplete="new-password" bag="updatePassword" />
                    <x-ui.field-error name="password_confirmation" bag="updatePassword" />
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <x-ui.button variant="primary">Simpan kata sandi</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('dashboard')">Batal</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>

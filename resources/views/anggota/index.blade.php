@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <x-ui.page-header title="Data anggota"
                      :subtitle="$anggota->total().' anggota '.($search !== null ? 'ditemukan' : 'terdaftar')">
        <x-slot:actions>
            <x-ui.button :href="route('anggota.create')" icon="user-plus">Tambah anggota</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none">
        {{-- Bilah pencarian (F3 §2, H.2.6): GET biasa, tombol Cari terlihat, tanpa JS. --}}
        <form method="GET" action="{{ route('anggota.index') }}" class="flex flex-wrap items-center gap-3 border-b border-stone-200 px-6 py-4">
            <label for="search" class="sr-only">Cari nama atau no. anggota</label>
            <div class="relative min-w-[16rem] flex-1">
                <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                <x-ui.input id="search" type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau no. anggota" autocomplete="off" ikon-kiri />
            </div>
            <x-ui.button variant="secondary">Cari</x-ui.button>
            @if ($search !== null)
                <a href="{{ route('anggota.index') }}" data-reset-pencarian class="px-1 text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
            @endif
        </form>

        @if ($anggota->isEmpty())
            @if ($search !== null)
                <x-ui.empty icon="magnifying-glass" :title="'Tidak ada anggota yang cocok dengan \''.$search.'\'.'">
                    <a href="{{ route('anggota.index') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset pencarian</a>
                </x-ui.empty>
            @else
                <x-ui.empty icon="users" title="Belum ada anggota" description="Tambahkan anggota pertama untuk mulai mencatat simpanan.">
                    <x-ui.button variant="secondary" :href="route('anggota.create')" icon="user-plus">Tambah anggota</x-ui.button>
                </x-ui.empty>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Anggota</th>
                            <th>NIK</th>
                            <th>No. telepon</th>
                            <th>Bergabung</th>
                            <th>Status</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $item)
                            <tr data-baris-anggota="{{ $item->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar-initial :nama="$item->nama" />
                                        <div class="min-w-0">
                                            <div class="font-medium text-stone-900">{{ $item->nama }}</div>
                                            <div class="font-mono text-xs text-stone-500">{{ $item->no_anggota }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="font-mono text-xs tabular-nums">{{ $item->nik }}</td>
                                <td class="tabular-nums">{{ $item->no_telepon ?? '–' }}</td>
                                <td class="whitespace-nowrap">{{ Format::tanggal($item->tanggal_bergabung) }}</td>
                                <td><x-status-anggota :status="$item->status" /></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-ui.button variant="ghost" size="sm" square :href="route('anggota.show', $item)" icon="eye"
                                                     aria-label="Lihat {{ $item->nama }}" title="Lihat" />
                                        <x-ui.button variant="ghost" size="sm" square :href="route('anggota.edit', $item)" icon="pencil-square"
                                                     aria-label="Ubah {{ $item->nama }}" title="Ubah" />
                                        @if ($item->isAktif())
                                            <x-ui.button variant="ghost" size="sm" square type="button" icon="no-symbol"
                                                         x-on:click="$dispatch('open-modal', 'nonaktifkan-{{ $item->id }}')"
                                                         aria-label="Nonaktifkan {{ $item->nama }}" title="Nonaktifkan" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($anggota->hasPages())
                <div class="border-t border-stone-200 px-6 py-4">
                    {{ $anggota->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>

    {{-- Modal di luar tabel (H.2.1): satu per anggota aktif di halaman ini. --}}
    @foreach ($anggota as $item)
        @if ($item->isAktif())
            @include('anggota.partials.modal-nonaktifkan', ['anggota' => $item])
        @endif
    @endforeach
</x-app-layout>

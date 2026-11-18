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
            {{-- F3b B.1: lebar kolom dikunci (colgroup + table-fixed); di layar sempit tabel bergulir mendatar. --}}
            <div class="overflow-x-auto">
                <table class="tabel min-w-[56rem] table-fixed">
                    {{-- Kolom Anggota tanpa lebar: mengisi sisa ruang setelah kolom Aksi yang lebarnya tetap (2 tombol + gap). --}}
                    <colgroup>
                        <col>
                        <col class="w-[16%]">
                        <col class="w-[13%]">
                        <col class="w-[12%]">
                        <col class="w-[14%]">
                        <col class="w-[10%]">
                        <col class="w-28">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Anggota</th>
                            <th>NIK</th>
                            <th>No. telepon</th>
                            <th>Bergabung</th>
                            <th class="num">Total simpanan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $item)
                            @php $aktif = $item->isAktif(); @endphp
                            {{-- B.5 / H.3.3: baris nonaktif redup (nama stone-500, teks lain stone-400); badge tidak diredupkan. --}}
                            <tr data-baris-anggota="{{ $item->id }}" @class(['text-stone-400' => ! $aktif]) @if (! $aktif) data-baris-nonaktif @endif>
                                <td>
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-ui.avatar-initial :nama="$item->nama" size="sm" />
                                        <div class="min-w-0">
                                            <a href="{{ route('anggota.show', $item) }}" data-tautan-detail
                                               @class([
                                                   'block truncate font-medium underline underline-offset-4 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30 rounded-sm',
                                                   'text-stone-900 decoration-stone-300 hover:decoration-stone-900' => $aktif,
                                                   'text-stone-500 decoration-stone-300 hover:decoration-stone-500' => ! $aktif,
                                               ])>{{ $item->nama }}</a>
                                            <div @class(['truncate font-mono text-xs', 'text-stone-500' => $aktif])>{{ $item->no_anggota }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap font-mono tabular-nums" data-nik-samar>{{ Format::nikSamar($item->nik) }}</td>
                                <td class="whitespace-nowrap tabular-nums">{{ $item->no_telepon }}</td>
                                <td class="whitespace-nowrap">{{ Format::tanggal($item->tanggal_bergabung) }}</td>
                                <td class="num whitespace-nowrap" data-total-simpanan>{{ Format::rupiah($item->total_simpanan) }}</td>
                                <td><x-status-anggota :status="$item->status" /></td>
                                <td>
                                    {{-- Revisi pasca-F3b: dua slot tombol ikon tetap (Ubah | Nonaktifkan); anggota nonaktif mendapat slot kosong agar pensil sejajar. --}}
                                    <div class="flex items-center gap-2">
                                        <x-ui.button variant="ghost" size="sm" square :href="route('anggota.edit', $item)" data-aksi-ubah
                                                     aria-label="Ubah data {{ $item->nama }}" title="Ubah data {{ $item->nama }}">
                                            <x-ui.icon name="pencil-square" class="h-4 w-4 text-stone-500" />
                                        </x-ui.button>
                                        @if ($aktif)
                                            <x-ui.button variant="ghost" size="sm" square type="button" data-aksi-nonaktifkan
                                                         class="group hover:!bg-red-50 hover:text-red-600"
                                                         x-on:click="$dispatch('open-modal', 'nonaktifkan-{{ $item->id }}')"
                                                         aria-label="Nonaktifkan {{ $item->nama }}" title="Nonaktifkan {{ $item->nama }}">
                                                <x-ui.icon name="no-symbol" class="h-4 w-4 text-stone-500 group-hover:text-red-600" />
                                            </x-ui.button>
                                        @else
                                            <span aria-hidden="true" class="inline-block h-8 w-8 shrink-0" data-slot-aksi-kosong></span>
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

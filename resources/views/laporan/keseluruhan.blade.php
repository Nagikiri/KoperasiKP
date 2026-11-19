@php
    use App\Support\Format;

    $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
    $adaFilter = $queryUnduh !== [];
@endphp

<x-app-layout>
    <x-ui.page-header title="Rekap & Laporan Keseluruhan" subtitle="Ringkasan simpanan, saldo per anggota, dan daftar transaksi.">
        <x-slot:actions>
            @include('laporan.partials.tombol-unduh', [
                'urlPdf' => route('laporan.keseluruhan.pdf', $queryUnduh),
                'urlExcel' => route('laporan.keseluruhan.excel', $queryUnduh),
            ])
        </x-slot:actions>
    </x-ui.page-header>

    <div class="space-y-6">
        {{-- Kartu filter (F5 A): nama field tetap; tombol "Tampilkan" karena ada validasi server (H.4.1). --}}
        <x-ui.card data-filter-laporan>
            <x-slot:header>
                <h2 class="flex items-center gap-2 text-base font-semibold text-stone-900">
                    <x-ui.icon name="funnel" class="h-5 w-5 text-stone-500" />
                    Filter
                </h2>
            </x-slot:header>

            <form method="GET" action="{{ route('laporan.keseluruhan') }}" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <x-ui.label for="tanggal_mulai" value="Tanggal mulai" />
                        <x-ui.input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ $filter['tanggal_mulai'] }}" class="mt-1.5" />
                        <x-ui.field-error name="tanggal_mulai" />
                    </div>

                    <div>
                        <x-ui.label for="tanggal_selesai" value="Tanggal selesai" />
                        <x-ui.input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ $filter['tanggal_selesai'] }}" class="mt-1.5" />
                        <x-ui.field-error name="tanggal_selesai" />
                    </div>

                    <div>
                        <x-ui.label for="jenis_simpanan_id" value="Jenis simpanan" />
                        <x-ui.select id="jenis_simpanan_id" name="jenis_simpanan_id" class="mt-1.5">
                            <option value="">Semua jenis</option>
                            @foreach ($jenisSimpanan as $jenis)
                                <option value="{{ $jenis->id }}" @selected($filter['jenis_simpanan_id'] === (string) $jenis->id)>{{ $jenis->nama_jenis }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field-error name="jenis_simpanan_id" />
                    </div>

                    <div>
                        <x-ui.label for="jenis_transaksi" value="Jenis transaksi" />
                        <x-ui.select id="jenis_transaksi" name="jenis_transaksi" class="mt-1.5">
                            <option value="">Semua transaksi</option>
                            <option value="setor" @selected($filter['jenis_transaksi'] === 'setor')>Setor</option>
                            <option value="tarik" @selected($filter['jenis_transaksi'] === 'tarik')>Tarik</option>
                        </x-ui.select>
                        <x-ui.field-error name="jenis_transaksi" />
                    </div>

                    <div>
                        <x-ui.label for="status_anggota" value="Status anggota" />
                        <x-ui.select id="status_anggota" name="status_anggota" class="mt-1.5">
                            <option value="">Semua status</option>
                            <option value="aktif" @selected($filter['status_anggota'] === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected($filter['status_anggota'] === 'nonaktif')>Nonaktif</option>
                        </x-ui.select>
                        <x-ui.field-error name="status_anggota" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-ui.button variant="primary">Tampilkan</x-ui.button>
                    @if ($adaFilter)
                        <a href="{{ route('laporan.keseluruhan') }}" data-reset-laporan class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset Filter</a>
                    @endif
                </div>
            </form>
        </x-ui.card>

        {{-- Ringkasan (F5 A, H.2.1–H.2.2): hanya membaca $laporan['total']; setor/tarik bertitik warna, saldo netral. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat size="sm" label="Total setor" titik="bg-setor" :value="Format::rupiah($laporan['total']['total_setor'])" data-stat="total_setor" />
            <x-ui.stat size="sm" label="Total tarik" titik="bg-tarik" :value="Format::rupiah($laporan['total']['total_tarik'])" data-stat="total_tarik" />
            <x-ui.stat size="sm" label="Saldo akhir periode" :value="Format::rupiah($laporan['total']['saldo_akhir_periode'])" data-stat="saldo_akhir_periode" />
        </div>

        {{-- Bingkai pratinjau (F5 A, H.2.3–H.2.4): isi = print preview PDF apa adanya; paginasi di kaki kartu, di luar dokumen. --}}
        <x-ui.card padding="none" data-kartu-pratinjau>
            <p class="px-6 pt-4 text-xs font-medium text-stone-500" data-label-pratinjau>Pratinjau cetak</p>
            <div class="laporan-pratinjau">
                <div class="min-w-[60rem]">
                    <div class="lap-dokumen" data-pratinjau-laporan="keseluruhan">
                        @include('laporan.partials.gaya')
                        @include('laporan.partials.kepala', [
                            'judul' => 'LAPORAN SIMPANAN KESELURUHAN',
                            'periode' => $laporan['periode']['label'],
                            'filter' => $laporan['filter_label'],
                        ])

                        @include('laporan.partials.keseluruhan-isi', ['laporan' => $laporan])

                        @include('laporan.partials.kaki', ['dicetakPada' => $laporan['dicetak_pada'], 'dicetakOleh' => $laporan['dicetak_oleh']])
                    </div>
                </div>
            </div>

            @if ($laporan['transaksi']->hasPages())
                <div class="border-t border-stone-200 px-6 py-4">
                    {{ $laporan['transaksi']->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>

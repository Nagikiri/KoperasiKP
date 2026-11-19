@php
    $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
    $tampilReset = $laporan !== null || $queryUnduh !== [];
@endphp

<x-app-layout>
    <x-ui.page-header title="Laporan per anggota" subtitle="Satu blok per anggota, lengkap dengan riwayat transaksinya.">
        @if ($laporan)
            <x-slot:actions>
                @include('laporan.partials.tombol-unduh', [
                    'urlPdf' => route('laporan.anggota.pdf', $queryUnduh),
                    'urlExcel' => route('laporan.anggota.excel', $queryUnduh),
                ])
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="space-y-6">
        {{--
            Kartu filter (F5 B, H.3–H.4). Pemilih anggota = partial pilih-anggota (opsi "Semua anggota", termasuk nonaktif,
            nilai awal dari $filter). Status hanya berlaku untuk semua anggota: disabled dari server saat anggota_id terisi,
            lalu mengikuti event `anggota-dipilih` dari partial. Field disabled tidak ikut terkirim (BR-15).
        --}}
        <x-ui.card data-filter-laporan>
            <x-slot:header>
                <h2 class="flex items-center gap-2 text-base font-semibold text-stone-900">
                    <x-ui.icon name="funnel" class="h-5 w-5 text-stone-500" />
                    Filter
                </h2>
            </x-slot:header>

            <form method="GET" action="{{ route('laporan.anggota') }}" class="space-y-5"
                  x-data="{ anggota: @js($filter['anggota_id'] ?? '') }"
                  x-on:anggota-dipilih="anggota = $event.detail.id">
                <input type="hidden" name="tampilkan" value="1">

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-1">
                        @include('transaksi.partials.pilih-anggota', [
                            'daftar' => $anggotaList,
                            'idAwal' => $filter['anggota_id'] ?? '',
                            'opsiSemua' => true,
                        ])
                    </div>

                    <div>
                        <x-ui.label for="status_anggota" value="Status anggota" />
                        <x-ui.select id="status_anggota" name="status_anggota" class="mt-1.5 disabled:cursor-not-allowed disabled:bg-stone-50 disabled:text-stone-400"
                                     x-bind:disabled="anggota !== ''" :disabled="$filter['anggota_id'] !== null"
                                     title="Hanya berlaku saat semua anggota dipilih">
                            <option value="">Semua status</option>
                            <option value="aktif" @selected($filter['status_anggota'] === 'aktif')>Aktif</option>
                            <option value="nonaktif" @selected($filter['status_anggota'] === 'nonaktif')>Nonaktif</option>
                        </x-ui.select>
                        <x-ui.field-error name="status_anggota" />
                    </div>

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
                </div>

                <div class="flex items-center gap-4">
                    <x-ui.button variant="primary">Tampilkan</x-ui.button>
                    @if ($tampilReset)
                        <a href="{{ route('laporan.anggota') }}" data-reset-laporan class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset Filter</a>
                    @endif
                </div>
            </form>
        </x-ui.card>

        @if ($laporan)
            {{-- Bingkai pratinjau (F5 A/B): isi = print preview PDF apa adanya. --}}
            <x-ui.card padding="none" data-kartu-pratinjau>
                <p class="px-6 pt-4 text-xs font-medium text-stone-500" data-label-pratinjau>Pratinjau cetak</p>
                <div class="laporan-pratinjau">
                    <div class="min-w-[60rem]">
                        <div class="lap-dokumen" data-pratinjau-laporan="per-anggota">
                            @include('laporan.partials.gaya')
                            @include('laporan.partials.kepala', [
                                'judul' => 'LAPORAN SIMPANAN PER ANGGOTA',
                                'periode' => $laporan['periode']['label'],
                                'filter' => $laporan['filter_label'],
                            ])

                            @include('laporan.partials.per-anggota-isi', ['laporan' => $laporan])

                            @include('laporan.partials.kaki', ['dicetakPada' => $laporan['dicetak_pada'], 'dicetakOleh' => $laporan['dicetak_oleh']])
                        </div>
                    </div>
                </div>
            </x-ui.card>
        @else
            <x-ui.card padding="none">
                <x-ui.empty icon="document-text" title="Pilih anggota dan periode" description="Kosongkan anggota untuk membuat laporan semua anggota." data-laporan-kosong />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>

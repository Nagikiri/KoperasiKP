@php
    $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
    $tampilReset = $laporan !== null || $queryUnduh !== [];

    // R1b C.1: mode awal mengikuti filter (anggota_id terisi = Satu anggota). Mode hanya tampilan; parameter tetap.
    $modeAwal = $filter['anggota_id'] !== null ? 'satu' : 'semua';
    $terpilihAwal = $anggotaList->first(fn ($anggota) => (string) $anggota->id === $filter['anggota_id']);
    $bergabungAwal = $modeAwal === 'satu' ? $terpilihAwal?->tanggal_bergabung?->toDateString() : null;
    $pilihanStatus = ['' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];
    $statusAwal = $filter['status_anggota'] ?? '';
    $kelasTab = '-mb-px inline-flex border-b-2 border-transparent pb-2 text-sm text-stone-500 transition-colors hover:text-stone-900';
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
            Kartu filter (F5 B, R1b C). "Laporan untuk" = tab Semua anggota | Satu anggota (hanya tampilan).
            Semua anggota: tab status (radio status_anggota), anggota_id disabled. Satu anggota: pemilih yang bisa dicari
            (termasuk nonaktif, tanpa opsi "Semua anggota"), status_anggota disabled. Field disabled tidak ikut terkirim (BR-15).
            Tanpa anggota terpilih di mode Satu, kiriman dibatalkan (defaultPrevented, jadi cegah-kirim-ganda tidak ikut).
        --}}
        <x-ui.card data-filter-laporan>
            <x-slot:header>
                <h2 class="flex items-center gap-2 text-base font-semibold text-stone-900">
                    <x-ui.icon name="funnel" class="h-5 w-5 text-stone-500" />
                    Filter
                </h2>
            </x-slot:header>

            <form method="GET" action="{{ route('laporan.anggota') }}" class="space-y-5"
                  x-data="{ mode: @js($modeAwal), anggota: @js($terpilihAwal ? (string) $terpilihAwal->id : ''), bergabung: @js((string) $terpilihAwal?->tanggal_bergabung?->toDateString()), galat: false }"
                  x-on:anggota-dipilih="anggota = $event.detail.id; bergabung = $event.detail.bergabung ?? ''; galat = false"
                  x-on:submit="if (mode === 'satu' &amp;&amp; anggota === '') { $event.preventDefault(); galat = true }">
                <input type="hidden" name="tampilkan" value="1">

                <div>
                    <span class="text-sm font-medium text-stone-700">Laporan untuk</span>
                    <div class="mt-1.5 flex gap-5 border-b border-stone-200" data-mode-laporan role="group" aria-label="Laporan untuk">
                        @foreach (['semua' => 'Semua anggota', 'satu' => 'Satu anggota'] as $nilai => $teks)
                            <button type="button" data-mode="{{ $nilai }}"
                                    aria-pressed="{{ $modeAwal === $nilai ? 'true' : 'false' }}"
                                    x-bind:aria-pressed="(mode === '{{ $nilai }}').toString()"
                                    x-on:click="mode = '{{ $nilai }}'; galat = false"
                                    class="{{ $kelasTab }} aria-pressed:border-arang aria-pressed:font-medium aria-pressed:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">{{ $teks }}</button>
                        @endforeach
                    </div>
                </div>

                {{-- Semua anggota: status sebagai tab garis bawah kecil (radio status_anggota, nilai sama seperti select lama). --}}
                <fieldset data-status-laporan x-show="mode === 'semua'" @if ($modeAwal !== 'semua') x-cloak @endif
                          x-bind:disabled="mode !== 'semua'" @disabled($modeAwal !== 'semua')>
                    <legend class="text-sm font-medium text-stone-700">Status anggota</legend>
                    <div class="mt-1.5 flex gap-4 border-b border-stone-200">
                        @foreach ($pilihanStatus as $nilai => $teks)
                            <label class="cursor-pointer">
                                <input type="radio" name="status_anggota" value="{{ $nilai }}" class="peer sr-only" @checked($statusAwal === $nilai)>
                                <span class="-mb-px inline-flex border-b-2 border-transparent pb-1.5 text-xs text-stone-500 transition-colors hover:text-stone-900 peer-checked:border-arang peer-checked:font-medium peer-checked:text-stone-900 peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.field-error name="status_anggota" />
                </fieldset>

                {{-- Satu anggota: pemilih yang bisa dicari, panel selebar isi, info bergabung/transaksi terakhir. --}}
                <div class="max-w-md" data-pemilih-laporan x-show="mode === 'satu'" @if ($modeAwal !== 'satu') x-cloak @endif>
                    @include('transaksi.partials.pilih-anggota', [
                        'daftar' => $anggotaList,
                        'idAwal' => $filter['anggota_id'] ?? '',
                        'panelLebar' => true,
                        'infoAnggota' => true,
                        'nonaktifJika' => "mode !== 'satu'",
                        'nonaktifAwal' => $modeAwal !== 'satu',
                    ])
                    <p class="mt-1.5 text-sm text-red-700" data-galat-pilih-anggota x-show="galat" x-cloak>Pilih anggota dulu.</p>
                </div>

                <x-ui.periode-cepat :periode="$periodeCepat" :mulai="$filter['tanggal_mulai']" :selesai="$filter['tanggal_selesai']"
                                    :min-awal="$bergabungAwal" batas-min="mode === 'satu' && bergabung ? bergabung : ''" />

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
                <x-ui.empty icon="document-text" title="Pilih semua atau satu anggota, lalu periodenya." data-laporan-kosong />
            </x-ui.card>
        @endif
    </div>
</x-app-layout>

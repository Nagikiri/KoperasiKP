@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <h1>Laporan per Anggota</h1>

    <form method="GET" action="{{ route('laporan.anggota') }}">
        <input type="hidden" name="tampilkan" value="1">

        <label for="anggota_id">Anggota</label>
        <select id="anggota_id" name="anggota_id">
            <option value="">Semua anggota</option>
            @foreach ($anggotaList as $anggota)
                <option value="{{ $anggota->id }}" @selected($filter['anggota_id'] === (string) $anggota->id)>
                    {{ $anggota->no_anggota }} - {{ Format::namaAnggota($anggota->nama, $anggota->status) }}
                </option>
            @endforeach
        </select>
        @error('anggota_id')
            <p>{{ $message }}</p>
        @enderror

        <label for="status_anggota">Status (khusus semua anggota)</label>
        <select id="status_anggota" name="status_anggota">
            <option value="">Semua status</option>
            <option value="aktif" @selected($filter['status_anggota'] === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($filter['status_anggota'] === 'nonaktif')>Nonaktif</option>
        </select>
        @error('status_anggota')
            <p>{{ $message }}</p>
        @enderror

        <label for="tanggal_mulai">Tanggal Mulai</label>
        <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ $filter['tanggal_mulai'] }}">
        @error('tanggal_mulai')
            <p>{{ $message }}</p>
        @enderror

        <label for="tanggal_selesai">Tanggal Selesai</label>
        <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ $filter['tanggal_selesai'] }}">
        @error('tanggal_selesai')
            <p>{{ $message }}</p>
        @enderror

        <button type="submit">Tampilkan</button>
    </form>

    @if ($laporan)
        @php
            $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
        @endphp

        @include('laporan.partials.tombol-unduh', [
            'urlPdf' => route('laporan.anggota.pdf', $queryUnduh),
            'urlExcel' => route('laporan.anggota.excel', $queryUnduh),
        ])

        {{-- Pratinjau = print preview PDF (struktur dan gaya sama, revisi R2). --}}
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
    @endif
</x-app-layout>

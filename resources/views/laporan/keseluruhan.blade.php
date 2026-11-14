@php
    use App\Support\Format;

    $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
@endphp

<x-app-layout>
    <h1>Rekap &amp; Laporan Keseluruhan</h1>

    <p>Rekap per jenis simpanan mencakup seluruh data sesuai filter. Tanpa filter, rekap mencakup seluruh data.</p>

    <form method="GET" action="{{ route('laporan.keseluruhan') }}">
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

        <label for="jenis_simpanan_id">Jenis Simpanan</label>
        <select id="jenis_simpanan_id" name="jenis_simpanan_id">
            <option value="">Semua jenis</option>
            @foreach ($jenisSimpanan as $jenis)
                <option value="{{ $jenis->id }}" @selected($filter['jenis_simpanan_id'] === (string) $jenis->id)>
                    {{ $jenis->nama_jenis }}
                </option>
            @endforeach
        </select>
        @error('jenis_simpanan_id')
            <p>{{ $message }}</p>
        @enderror

        <label for="jenis_transaksi">Jenis Transaksi</label>
        <select id="jenis_transaksi" name="jenis_transaksi">
            <option value="">Semua transaksi</option>
            <option value="setor" @selected($filter['jenis_transaksi'] === 'setor')>Setor</option>
            <option value="tarik" @selected($filter['jenis_transaksi'] === 'tarik')>Tarik</option>
        </select>
        @error('jenis_transaksi')
            <p>{{ $message }}</p>
        @enderror

        <label for="status_anggota">Status Anggota</label>
        <select id="status_anggota" name="status_anggota">
            <option value="">Semua status</option>
            <option value="aktif" @selected($filter['status_anggota'] === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($filter['status_anggota'] === 'nonaktif')>Nonaktif</option>
        </select>
        @error('status_anggota')
            <p>{{ $message }}</p>
        @enderror

        <button type="submit">Tampilkan</button>
    </form>

    @include('laporan.partials.tombol-unduh', [
        'urlPdf' => route('laporan.keseluruhan.pdf', $queryUnduh),
        'urlExcel' => route('laporan.keseluruhan.excel', $queryUnduh),
    ])

    {{-- Pratinjau = print preview PDF (struktur dan gaya sama, revisi R2). --}}
    <div class="lap-dokumen" data-pratinjau-laporan="keseluruhan">
        @include('laporan.partials.gaya')
        @include('laporan.partials.kepala', [
            'judul' => 'LAPORAN SIMPANAN KESELURUHAN',
            'periode' => $laporan['periode']['label'],
            'filter' => $laporan['filter_label'],
        ])

        @include('laporan.partials.keseluruhan-isi', ['laporan' => $laporan])

        {{ $laporan['transaksi']->links() }}

        @include('laporan.partials.kaki', ['dicetakPada' => $laporan['dicetak_pada'], 'dicetakOleh' => $laporan['dicetak_oleh']])
    </div>
</x-app-layout>

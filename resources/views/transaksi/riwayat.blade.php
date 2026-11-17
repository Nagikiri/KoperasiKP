@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <h1>Riwayat Transaksi</h1>

    <p>
        <a href="{{ route('transaksi.setor.create') }}">Setor</a>
        <a href="{{ route('transaksi.tarik.create') }}">Tarik</a>
    </p>

    <form method="GET" action="{{ route('transaksi.riwayat') }}">
        <label for="anggota_id">Anggota</label>
        <select id="anggota_id" name="anggota_id">
            <option value="">Semua anggota</option>
            @foreach ($anggotaList as $anggota)
                <option value="{{ $anggota->id }}" @selected($filter['anggota_id'] === (string) $anggota->id)>
                    {{ $anggota->no_anggota }} - {{ Format::namaAnggota($anggota->nama, $anggota->status) }}
                </option>
            @endforeach
        </select>

        <label for="jenis_simpanan_id">Jenis Simpanan</label>
        <select id="jenis_simpanan_id" name="jenis_simpanan_id">
            <option value="">Semua jenis</option>
            @foreach ($jenisSimpanan as $jenis)
                <option value="{{ $jenis->id }}" @selected($filter['jenis_simpanan_id'] === (string) $jenis->id)>
                    {{ $jenis->nama_jenis }}
                </option>
            @endforeach
        </select>

        {{-- F2d H.2.1: filter dari dashboard tetap terbawa saat "Terapkan Filter" (sementara, diganti bilah filter F4). --}}
        @foreach (['cari', 'jenis_transaksi', 'rentang'] as $kunci)
            @if ($filter[$kunci] !== null)
                <input type="hidden" name="{{ $kunci }}" value="{{ $filter[$kunci] }}">
            @endif
        @endforeach

        <button type="submit">Terapkan Filter</button>
        @if ($filter['anggota_id'] !== null || $filter['jenis_simpanan_id'] !== null)
            <a href="{{ route('transaksi.riwayat') }}">Reset Filter</a>
        @endif
    </form>

    @php
        $filterDashboard = array_filter([
            $filter['jenis_transaksi'] !== null ? ($filter['jenis_transaksi'] === \App\Models\TransaksiSimpanan::SETOR ? 'Setoran' : 'Penarikan') : null,
            $filter['rentang'] !== null ? ['hari_ini' => 'Hari ini', '7_hari' => '7 hari', '30_hari' => '30 hari', 'bulan_ini' => 'Bulan ini'][$filter['rentang']] : null,
            $filter['cari'] !== null ? '"'.$filter['cari'].'"' : null,
        ]);
    @endphp
    @if ($filterDashboard)
        <p data-filter-dashboard>
            Juga difilter: {{ implode(' · ', $filterDashboard) }}
            <a href="{{ route('transaksi.riwayat', array_filter(['anggota_id' => $filter['anggota_id'], 'jenis_simpanan_id' => $filter['jenis_simpanan_id']], fn ($nilai) => $nilai !== null)) }}">Hapus</a>
        </p>
    @endif

    @if ($riwayat->isEmpty())
        <p>Belum ada transaksi.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Anggota</th>
                    <th>Nama</th>
                    <th>Jenis Simpanan</th>
                    <th>Jenis Transaksi</th>
                    <th>Jumlah</th>
                    <th>Saldo Akhir</th>
                    <th>Dicatat Oleh</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($riwayat as $transaksi)
                    <tr>
                        <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                        <td>{{ $transaksi->anggota->no_anggota }}</td>
                        <td><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></td>
                        <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                        <td>{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                        <td>{{ Format::rupiah($transaksi->jumlah) }}</td>
                        <td>{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                        <td>{{ $transaksi->staff->nama }}</td>
                        <td>{{ $transaksi->keterangan ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{ $riwayat->links() }}
</x-app-layout>

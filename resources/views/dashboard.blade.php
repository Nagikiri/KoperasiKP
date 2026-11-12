@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <h1>Dashboard</h1>

    <p>Total anggota aktif: {{ $totalAnggotaAktif }}</p>

    <h2>Saldo per Jenis Simpanan</h2>
    <table>
        <thead>
            <tr>
                <th>Jenis Simpanan</th>
                <th>Total Setor</th>
                <th>Total Tarik</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rekapSaldo as $rekap)
                <tr>
                    <td>{{ $rekap['nama_jenis'] }}</td>
                    <td>{{ Format::rupiah($rekap['total_setor']) }}</td>
                    <td>{{ Format::rupiah($rekap['total_tarik']) }}</td>
                    <td>{{ Format::rupiah($rekap['saldo']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Total Saldo</th>
                <th>{{ Format::rupiah($totalSaldo) }}</th>
            </tr>
        </tfoot>
    </table>

    <h2>Transaksi Terbaru</h2>
    @if ($transaksiTerbaru->isEmpty())
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
                    <th>Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaksiTerbaru as $transaksi)
                    <tr>
                        <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                        <td>{{ $transaksi->anggota->no_anggota }}</td>
                        <td>{{ $transaksi->anggota->nama }}</td>
                        <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                        <td>{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                        <td>{{ Format::rupiah($transaksi->jumlah) }}</td>
                        <td>{{ $transaksi->staff->nama }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-app-layout>

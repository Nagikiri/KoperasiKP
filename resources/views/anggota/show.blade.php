@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <h1>Detail Anggota</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    <h2>Identitas</h2>
    <table>
        <tr><th>No. Anggota</th><td>{{ $anggota->no_anggota }}</td></tr>
        <tr><th>Nama</th><td>{{ $anggota->nama }}</td></tr>
        <tr><th>NIK</th><td>{{ $anggota->nik }}</td></tr>
        <tr><th>Alamat</th><td>{{ $anggota->alamat ?? '-' }}</td></tr>
        <tr><th>No. Telepon</th><td>{{ $anggota->no_telepon ?? '-' }}</td></tr>
        <tr><th>Tanggal Bergabung</th><td>{{ Format::tanggal($anggota->tanggal_bergabung) }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($anggota->status) }}</td></tr>
    </table>

    <p>
        <a href="{{ route('anggota.edit', $anggota) }}">Ubah</a>
        <a href="{{ route('anggota.index') }}">Kembali ke Data Anggota</a>
    </p>

    <h2>Saldo Simpanan</h2>
    <table>
        <thead>
            <tr>
                <th>Jenis Simpanan</th>
                <th>Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($saldoPerJenis as $saldo)
                <tr>
                    <td>{{ $saldo['nama_jenis'] }}</td>
                    <td>{{ Format::rupiah($saldo['saldo']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>Total Saldo</th>
                <th>{{ Format::rupiah($totalSaldo) }}</th>
            </tr>
        </tfoot>
    </table>

    <h2>Riwayat Transaksi</h2>
    @if ($riwayatTransaksi->isEmpty())
        <p>Belum ada transaksi.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Simpanan</th>
                    <th>Jenis Transaksi</th>
                    <th>Jumlah</th>
                    <th>Saldo Akhir</th>
                    <th>Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($riwayatTransaksi as $transaksi)
                    <tr>
                        <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                        <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                        <td>{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                        <td>{{ Format::rupiah($transaksi->jumlah) }}</td>
                        <td>{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                        <td>{{ $transaksi->staff->nama }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{ $riwayatTransaksi->links() }}
</x-app-layout>

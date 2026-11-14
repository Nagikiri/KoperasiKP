{{--
    Isi laporan keseluruhan (revisi R2), dipakai pratinjau web dan PDF supaya
    strukturnya sama: rekap per jenis (FR-15), saldo per anggota, transaksi.
    Variabel: $laporan (hasil LaporanService::keseluruhan()); $laporan['transaksi']
    boleh Collection (PDF) atau paginator (pratinjau).
--}}
@php
    use App\Models\Anggota;
    use App\Support\Format;

    $transaksiList = $laporan['transaksi'];
    $nomorAwal = method_exists($transaksiList, 'firstItem') ? ($transaksiList->firstItem() ?? 1) : 1;
    $kolomSaldo = 6 + $laporan['ringkasan']->count();
@endphp

<p class="lap-bagian">REKAP PER JENIS SIMPANAN</p>
<table class="lap-tabel" data-tabel="ringkasan">
    <thead>
        <tr>
            <th>Jenis Simpanan</th>
            <th>Total Setor</th>
            <th>Total Tarik</th>
            <th>Saldo Akhir Periode</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($laporan['ringkasan'] as $baris)
            <tr>
                <td>{{ $baris['nama_jenis'] }}</td>
                <td class="lap-angka">{{ Format::rupiah($baris['total_setor']) }}</td>
                <td class="lap-angka">{{ Format::rupiah($baris['total_tarik']) }}</td>
                <td class="lap-angka">{{ Format::rupiah($baris['saldo_akhir_periode']) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th>Total</th>
            <th class="lap-angka">{{ Format::rupiah($laporan['total']['total_setor']) }}</th>
            <th class="lap-angka">{{ Format::rupiah($laporan['total']['total_tarik']) }}</th>
            <th class="lap-angka">{{ Format::rupiah($laporan['total']['saldo_akhir_periode']) }}</th>
        </tr>
    </tfoot>
</table>

<p class="lap-bagian">SALDO PER ANGGOTA</p>
<table class="lap-tabel" data-tabel="saldo-anggota">
    <thead>
        <tr>
            <th>No</th>
            <th>No. Anggota</th>
            <th>Nama</th>
            <th>Status</th>
            <th>Tanggal Bergabung</th>
            @foreach ($laporan['ringkasan'] as $jenis)
                <th>{{ $jenis['nama_jenis'] }}</th>
            @endforeach
            <th>Total Saldo</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($laporan['saldo_anggota'] as $anggota)
            <tr>
                <td class="lap-tengah">{{ $loop->iteration }}</td>
                <td>{{ $anggota['no_anggota'] }}</td>
                <td>{{ $anggota['nama'] }}</td>
                <td class="lap-tengah {{ $anggota['status'] === Anggota::STATUS_AKTIF ? 'lap-hijau' : 'lap-merah' }}">{{ Format::statusAnggota($anggota['status']) }}</td>
                <td>{{ Format::tanggal($anggota['tanggal_bergabung']) }}</td>
                @foreach ($anggota['saldo_per_jenis'] as $saldo)
                    <td class="lap-angka">{{ Format::rupiah($saldo['saldo']) }}</td>
                @endforeach
                <td class="lap-angka">{{ Format::rupiah($anggota['total_saldo']) }}</td>
            </tr>
        @empty
            <tr class="lap-kosong">
                <td colspan="{{ $kolomSaldo }}">Tidak ada data sesuai filter.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<p class="lap-bagian">TRANSAKSI</p>
<table class="lap-tabel" data-tabel="transaksi">
    <thead>
        <tr>
            <th>No</th>
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
        @forelse ($transaksiList as $transaksi)
            <tr>
                <td class="lap-tengah">{{ $nomorAwal + $loop->index }}</td>
                <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                <td>{{ $transaksi->anggota->no_anggota }}</td>
                <td><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></td>
                <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                <td class="{{ $transaksi->jenis_transaksi === 'setor' ? 'lap-hijau' : 'lap-merah' }}">{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                <td class="lap-angka">{{ Format::rupiah($transaksi->jumlah) }}</td>
                <td class="lap-angka">{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                <td>{{ $transaksi->staff->nama }}</td>
                <td>{{ $transaksi->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr class="lap-kosong">
                <td colspan="10">Tidak ada data sesuai filter.</td>
            </tr>
        @endforelse
    </tbody>
</table>

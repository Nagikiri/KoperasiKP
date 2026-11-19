{{--
    Isi PDF Riwayat transaksi (F5 H.5.4–H.5.6): tabel transaksi urut lama → baru, lalu ringkasan.
    Tanpa NIK. Anggota nonaktif: "(Nonaktif)" merah di kolom nama. Warna jenis transaksi sama dengan PDF laporan.
    Variabel: $transaksi (Collection TransaksiSimpanan + anggota, jenisSimpanan, staff), $ringkasan (jumlah, total_setor, total_tarik).
--}}
@php
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    $bersih = round($ringkasan['total_setor'] - $ringkasan['total_tarik'], 2);
@endphp

<table class="lap-tabel" data-tabel="riwayat">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>No. Anggota</th>
            <th>Nama Anggota</th>
            <th>Jenis Transaksi</th>
            <th>Jenis Simpanan</th>
            <th>Jumlah</th>
            <th>Saldo Setelah Transaksi</th>
            <th>Dicatat Oleh</th>
            <th>Catatan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transaksi as $baris)
            @php($setor = $baris->jenis_transaksi === TransaksiSimpanan::SETOR)
            <tr data-transaksi="{{ $baris->id }}">
                <td>{{ Format::tanggal($baris->tanggal_transaksi) }}</td>
                <td>{{ $baris->anggota->no_anggota }}</td>
                <td>{{ $baris->anggota->nama }}@unless ($baris->anggota->isAktif()) <span class="lap-merah" data-penanda-nonaktif>(Nonaktif)</span>@endunless</td>
                <td class="{{ $setor ? 'lap-hijau' : 'lap-merah' }}">{{ $setor ? 'Setor' : 'Tarik' }}</td>
                <td>{{ $baris->jenisSimpanan->nama_jenis }}</td>
                <td class="lap-angka">{{ Format::rupiah($baris->jumlah) }}</td>
                <td class="lap-angka">{{ Format::rupiah($baris->saldo_akhir) }}</td>
                <td>{{ $baris->staff->nama }}</td>
                <td>{{ $baris->keterangan ?? '-' }}</td>
            </tr>
        @empty
            <tr class="lap-kosong">
                <td colspan="9">Tidak ada transaksi sesuai filter.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<p class="lap-bagian">RINGKASAN</p>
<table class="lap-tabel" data-tabel="ringkasan-riwayat" style="width: 50%;">
    <tbody>
        <tr><td>Jumlah transaksi</td><td class="lap-angka" data-ringkasan="jumlah">{{ number_format($ringkasan['jumlah'], 0, ',', '.') }}</td></tr>
        <tr><td>Total setor</td><td class="lap-angka" data-ringkasan="total_setor">{{ Format::rupiah($ringkasan['total_setor']) }}</td></tr>
        <tr><td>Total tarik</td><td class="lap-angka" data-ringkasan="total_tarik">{{ Format::rupiah($ringkasan['total_tarik']) }}</td></tr>
        <tr><td><strong>Bersih</strong></td><td class="lap-angka" data-ringkasan="bersih"><strong>{{ $bersih > 0 ? '+' : ($bersih < 0 ? '-' : '') }}{{ Format::rupiah(abs($bersih)) }}</strong></td></tr>
    </tbody>
</table>

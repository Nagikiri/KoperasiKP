{{--
    Isi laporan per anggota (revisi R6), dipakai pratinjau web dan PDF supaya
    strukturnya sama. SATU BLOK PER ANGGOTA, masing-masing berisi tabel
    terpisah dengan kolomnya sendiri:
      1. pita judul anggota (no, no_anggota — nama | Bergabung | Status)
         (+ keterangan saldo nonaktif R5 bila perlu)
      2. tabel saldo (4 kolom sama lebar)
      3. pita "RIWAYAT TRANSAKSI — nama (no_anggota)"
      4. tabel riwayat dengan header sendiri (berulang di halaman PDF berikutnya)
      5. ringkasan total setor/tarik periode
      6. jarak sebelum blok berikutnya
    Gaya di partial `gaya`. Variabel: $laporan (hasil LaporanService::perAnggota()).
--}}
@php
    use App\Models\Anggota;
    use App\Support\Format;
@endphp

@forelse ($laporan['anggota'] as $anggota)
    @php($aktif = $anggota['status'] === Anggota::STATUS_AKTIF)
    <div class="lap-blok" data-anggota="{{ $anggota['no_anggota'] }}">
        <table class="lap-tabel lap-pita-judul">
            <tr>
                <td data-pita-judul>{{ $loop->iteration }}. {{ $anggota['no_anggota'] }} — {{ $anggota['nama'] }}&nbsp;&nbsp;|&nbsp;&nbsp;Bergabung: {{ Format::tanggal($anggota['tanggal_bergabung']) }}&nbsp;&nbsp;|&nbsp;&nbsp;Status: <span class="{{ $aktif ? 'lap-hijau' : 'lap-merah' }}">{{ Format::statusAnggota($anggota['status']) }}</span></td>
            </tr>
            @if (Anggota::perluKeteranganSaldo($anggota['status'], $anggota['total_saldo']))
                <tr class="lap-catatan-nonaktif">
                    <td data-keterangan-saldo-nonaktif>{{ Anggota::KETERANGAN_SALDO_NONAKTIF }}</td>
                </tr>
            @endif
        </table>

        <table class="lap-tabel lap-saldo">
            <thead>
                <tr>
                    @foreach ($anggota['saldo_per_jenis'] as $saldo)
                        <th>{{ $saldo['nama_jenis'] }}</th>
                    @endforeach
                    <th>Total Saldo</th>
                </tr>
            </thead>
            <tbody>
                <tr class="lap-nilai-saldo">
                    @foreach ($anggota['saldo_per_jenis'] as $saldo)
                        <td class="lap-angka">{{ Format::rupiah($saldo['saldo']) }}</td>
                    @endforeach
                    <td class="lap-angka">{{ Format::rupiah($anggota['total_saldo']) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="lap-tabel lap-pita-riwayat">
            <tr>
                <td>RIWAYAT TRANSAKSI — {{ $anggota['nama'] }} ({{ $anggota['no_anggota'] }})</td>
            </tr>
        </table>

        <table class="lap-tabel lap-riwayat">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Jenis Simpanan</th>
                    <th>Jenis Transaksi</th>
                    <th>Jumlah</th>
                    <th>Saldo Akhir</th>
                    <th>Dicatat Oleh</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($anggota['transaksi'] as $transaksi)
                    <tr data-transaksi="{{ $transaksi->id }}">
                        <td class="lap-tengah">{{ $loop->iteration }}</td>
                        <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                        <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                        <td class="{{ $transaksi->jenis_transaksi === 'setor' ? 'lap-hijau' : 'lap-merah' }}">{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                        <td class="lap-angka">{{ Format::rupiah($transaksi->jumlah) }}</td>
                        <td class="lap-angka">{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                        <td>{{ $transaksi->staff->nama }}</td>
                        <td>{{ $transaksi->keterangan ?? '-' }}</td>
                    </tr>
                @empty
                    <tr class="lap-kosong">
                        <td colspan="8">Tidak ada transaksi pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table class="lap-tabel lap-ringkasan-anggota">
            <tr>
                <td data-total-setor-periode>Total setor periode: {{ Format::rupiah($anggota['total_setor_periode']) }}</td>
                <td data-total-tarik-periode>Total tarik periode: {{ Format::rupiah($anggota['total_tarik_periode']) }}</td>
            </tr>
        </table>
    </div>
@empty
    <table class="lap-tabel">
        <tr class="lap-kosong">
            <td>Tidak ada data sesuai filter.</td>
        </tr>
    </table>
@endforelse

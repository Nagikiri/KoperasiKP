@php
    use App\Support\Format;

    $kartu = 'bg-white rounded-lg shadow p-4';
    $tombol = 'inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150';
@endphp

<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>

        {{-- Kartu ringkasan --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="{{ $kartu }}">
                <p class="text-sm text-gray-500">Anggota Aktif</p>
                <p class="text-2xl font-semibold" data-ringkasan="aktif">{{ $ringkasanAnggota['aktif'] }}</p>
                <p class="text-xs text-gray-500">{{ $ringkasanAnggota['baru_bulan_ini'] }} anggota baru bulan ini</p>
            </div>
            <div class="{{ $kartu }}">
                <p class="text-sm text-gray-500">Anggota Nonaktif</p>
                <p class="text-2xl font-semibold" data-ringkasan="nonaktif">{{ $ringkasanAnggota['nonaktif'] }}</p>
            </div>
            <div class="{{ $kartu }}">
                <p class="text-sm text-gray-500">Total Saldo Simpanan</p>
                <p class="text-2xl font-semibold">{{ Format::rupiah($totalSaldo) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach ($rekapSaldo as $rekap)
                <div class="{{ $kartu }}">
                    <p class="text-sm text-gray-500">{{ $rekap['nama_jenis'] }}</p>
                    <p class="text-lg font-semibold">{{ Format::rupiah($rekap['saldo']) }}</p>
                    <p class="text-xs text-gray-500">Setor {{ Format::rupiah($rekap['total_setor']) }} · Tarik {{ Format::rupiah($rekap['total_tarik']) }}</p>
                </div>
            @endforeach
        </div>

        {{-- Aktivitas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach (['Aktivitas Hari Ini' => $aktivitasHariIni, 'Aktivitas Bulan Ini' => $aktivitasBulanIni] as $judul => $aktivitas)
                <div class="{{ $kartu }}">
                    <h2 class="font-semibold text-gray-800">{{ $judul }}</h2>
                    <table class="w-full text-sm mt-2">
                        <tr><td>Jumlah transaksi</td><td class="text-right">{{ $aktivitas['jumlah_transaksi'] }}</td></tr>
                        <tr><td>Total setor</td><td class="text-right">{{ Format::rupiah($aktivitas['total_setor']) }}</td></tr>
                        <tr><td>Total tarik</td><td class="text-right">{{ Format::rupiah($aktivitas['total_tarik']) }}</td></tr>
                    </table>
                </div>
            @endforeach
        </div>

        {{-- Aksi cepat --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('anggota.create') }}" class="{{ $tombol }}">Tambah Anggota</a>
            <a href="{{ route('transaksi.setor.create') }}" class="{{ $tombol }}">Setor</a>
            <a href="{{ route('transaksi.tarik.create') }}" class="{{ $tombol }}">Tarik</a>
            <a href="{{ route('laporan.keseluruhan') }}" class="{{ $tombol }}">Laporan</a>
        </div>

        {{-- Tren 6 bulan (grafik di tahap frontend) --}}
        <div class="{{ $kartu }}">
            <h2 class="font-semibold text-gray-800">Tren 6 Bulan Terakhir</h2>
            <table class="w-full text-sm mt-2">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th>Bulan</th>
                        <th class="text-right">Total Setor</th>
                        <th class="text-right">Total Tarik</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trenBulanan as $tren)
                        <tr>
                            <td>{{ $tren['bulan'] }}</td>
                            <td class="text-right">{{ Format::rupiah($tren['total_setor']) }}</td>
                            <td class="text-right">{{ Format::rupiah($tren['total_tarik']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Belum setor wajib (informasi saja) --}}
        <div class="{{ $kartu }}">
            <h2 class="font-semibold text-gray-800">Belum setor Simpanan Wajib bulan ini ({{ $jumlahBelumSetorWajib }})</h2>
            @if ($belumSetorWajib->isEmpty())
                <p class="text-sm text-gray-500 mt-2">Semua anggota aktif sudah setor Simpanan Wajib bulan ini.</p>
            @else
                <ul class="text-sm mt-2 space-y-1">
                    @foreach ($belumSetorWajib as $anggota)
                        <li>
                            {{ $anggota->no_anggota }} - {{ $anggota->nama }}
                            <a href="{{ route('transaksi.setor.create', ['anggota_id' => $anggota->id]) }}" class="text-indigo-600 hover:underline">Setor</a>
                        </li>
                    @endforeach
                </ul>
                @if ($jumlahBelumSetorWajib > $belumSetorWajib->count())
                    <p class="text-xs text-gray-500 mt-2">Menampilkan {{ $belumSetorWajib->count() }} dari {{ $jumlahBelumSetorWajib }} anggota.</p>
                @endif
            @endif
        </div>

        {{-- Transaksi terbaru --}}
        <div class="{{ $kartu }}">
            <h2 class="font-semibold text-gray-800">Transaksi Terbaru</h2>
            @if ($transaksiTerbaru->isEmpty())
                <p class="text-sm text-gray-500 mt-2">Belum ada transaksi.</p>
            @else
                <table class="w-full text-sm mt-2">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th>Tanggal</th>
                            <th>No. Anggota</th>
                            <th>Nama</th>
                            <th>Jenis Simpanan</th>
                            <th>Jenis Transaksi</th>
                            <th class="text-right">Jumlah</th>
                            <th>Dicatat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaksiTerbaru as $transaksi)
                            <tr>
                                <td>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                <td>{{ $transaksi->anggota->no_anggota }}</td>
                                <td><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></td>
                                <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                                <td>{{ ucfirst($transaksi->jenis_transaksi) }}</td>
                                <td class="text-right">{{ Format::rupiah($transaksi->jumlah) }}</td>
                                <td>{{ $transaksi->staff->nama }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>

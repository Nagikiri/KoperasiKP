@php
    use App\Models\JenisSimpanan;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    // Warna segmen komposisi saldo per jenis (F2 kartu A).
    $warnaJenis = [
        JenisSimpanan::POKOK => 'bg-arang',
        JenisSimpanan::WAJIB => 'bg-emas',
        JenisSimpanan::SUKARELA => 'bg-stone-400',
    ];

    $adaTren = $trenBulanan->contains(fn ($tren) => (float) $tren['total_setor'] != 0 || (float) $tren['total_tarik'] != 0);

    $aksiCepat = [
        ['route' => 'anggota.create', 'ikon' => 'user-plus', 'judul' => 'Tambah anggota', 'keterangan' => 'Daftarkan anggota baru'],
        ['route' => 'transaksi.setor.create', 'ikon' => 'arrow-down-tray', 'judul' => 'Catat setoran', 'keterangan' => 'Pokok, wajib, atau sukarela'],
        ['route' => 'transaksi.tarik.create', 'ikon' => 'arrow-up-tray', 'judul' => 'Catat penarikan', 'keterangan' => 'Simpanan sukarela'],
        ['route' => 'laporan.keseluruhan', 'ikon' => 'chart-bar', 'judul' => 'Buat laporan', 'keterangan' => 'Rekap, PDF, atau Excel'],
    ];
@endphp

<x-app-layout>
    <x-ui.page-header title="Dashboard" :subtitle="'Diperbarui '.Format::tanggalWaktu(now())" />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom utama --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- A. Total saldo simpanan --}}
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-stone-500">Total saldo simpanan</p>
                        <p class="mt-2 text-4xl font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($totalSaldo) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button :href="route('transaksi.setor.create')" icon="arrow-down-tray">Catat setoran</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('transaksi.tarik.create')" icon="arrow-up-tray">Catat penarikan</x-ui.button>
                    </div>
                </div>

                @if ((float) $totalSaldo > 0)
                    <div class="mt-6 flex h-2 w-full overflow-hidden rounded-full bg-stone-100" data-komposisi-saldo>
                        @foreach ($rekapSaldo as $rekap)
                            @php $persen = max(0, (float) $rekap['saldo']) / (float) $totalSaldo * 100; @endphp
                            @if ($persen > 0)
                                <div class="h-full {{ $warnaJenis[$rekap['nama_jenis']] ?? 'bg-stone-300' }}" style="width: {{ round($persen, 2) }}%"></div>
                            @endif
                        @endforeach
                    </div>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($rekapSaldo as $rekap)
                            <li class="flex items-center justify-between gap-4">
                                <span class="flex items-center gap-2 text-stone-600">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $warnaJenis[$rekap['nama_jenis']] ?? 'bg-stone-300' }}"></span>
                                    {{ $rekap['nama_jenis'] }}
                                </span>
                                <span class="font-medium text-stone-900 tabular-nums">{{ Format::rupiah($rekap['saldo']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="mt-6 h-2 w-full rounded-full bg-stone-200" data-komposisi-saldo></div>
                    <p class="mt-3 text-sm text-stone-500">Belum ada saldo tercatat.</p>
                @endif
            </x-ui.card>

            {{-- B. Ringkasan --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <x-ui.stat label="Anggota aktif" :value="$ringkasanAnggota['aktif']">
                    <p>{{ $ringkasanAnggota['baru_bulan_ini'] }} anggota baru bulan ini</p>
                    <p class="mt-0.5">{{ $ringkasanAnggota['nonaktif'] }} anggota nonaktif</p>
                </x-ui.stat>

                @foreach (['Hari ini' => $aktivitasHariIni, 'Bulan ini' => $aktivitasBulanIni] as $label => $aktivitas)
                    <x-ui.stat :label="$label" :value="$aktivitas['jumlah_transaksi'].' transaksi'">
                        <p class="flex justify-between gap-2">
                            <span>Setor</span>
                            <span class="font-medium text-emerald-700 tabular-nums">{{ Format::rupiah($aktivitas['total_setor']) }}</span>
                        </p>
                        <p class="mt-0.5 flex justify-between gap-2">
                            <span>Tarik</span>
                            <span class="font-medium text-red-700 tabular-nums">{{ Format::rupiah($aktivitas['total_tarik']) }}</span>
                        </p>
                    </x-ui.stat>
                @endforeach
            </div>

            {{-- C. Tren 6 bulan --}}
            <x-ui.card>
                <x-slot:header>
                    <div>
                        <h2 class="text-base font-semibold text-stone-900">Setoran dan penarikan</h2>
                        <p class="text-xs text-stone-500">6 bulan terakhir</p>
                    </div>
                </x-slot:header>

                @if ($adaTren)
                    <div class="relative h-[260px]">
                        <canvas id="grafik-tren" role="img" aria-label="Grafik setoran dan penarikan 6 bulan terakhir"></canvas>
                    </div>
                    <script type="application/json" id="data-tren">@json($trenBulanan)</script>

                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer text-stone-600 hover:text-stone-900">Lihat angka</summary>
                        <table class="tabel mt-3">
                            <thead>
                                <tr>
                                    <th>Bulan</th>
                                    <th class="num">Setor</th>
                                    <th class="num">Tarik</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trenBulanan as $tren)
                                    <tr>
                                        <td>{{ $tren['bulan'] }}</td>
                                        <td class="num">{{ Format::rupiah($tren['total_setor']) }}</td>
                                        <td class="num">{{ Format::rupiah($tren['total_tarik']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @else
                    <x-ui.empty icon="chart-bar" title="Belum ada transaksi dalam 6 bulan terakhir." />
                @endif
            </x-ui.card>
        </div>

        {{-- Kolom kanan --}}
        <div class="space-y-6">
            {{-- Aksi cepat --}}
            <x-ui.card padding="none" data-aksi-cepat>
                <x-slot:header>
                    <h2 class="text-base font-semibold text-stone-900">Aksi cepat</h2>
                </x-slot:header>

                <ul class="divide-y divide-stone-100">
                    @foreach ($aksiCepat as $aksi)
                        <li>
                            <a href="{{ route($aksi['route']) }}" class="flex items-center gap-3 px-6 py-4 hover:bg-stone-50 focus:outline-none focus-visible:bg-stone-50">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-stone-100 text-stone-700">
                                    <x-ui.icon :name="$aksi['ikon']" class="w-5 h-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-stone-900">{{ $aksi['judul'] }}</span>
                                    <span class="block truncate text-xs text-stone-500">{{ $aksi['keterangan'] }}</span>
                                </span>
                                <x-ui.icon name="chevron-right" class="w-4 h-4 text-stone-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            {{-- D. Belum setor wajib --}}
            <x-ui.card padding="none">
                <x-slot:header>
                    <h2 class="text-base font-semibold text-stone-900">Belum setor wajib</h2>
                    <x-ui.badge tone="warning">{{ $jumlahBelumSetorWajib }}</x-ui.badge>
                </x-slot:header>

                @if ($belumSetorWajib->isEmpty())
                    <x-ui.empty icon="check-circle" title="Semua anggota aktif sudah setor wajib bulan ini." />
                @else
                    <ul class="divide-y divide-stone-100" data-belum-setor-wajib>
                        @foreach ($belumSetorWajib as $anggota)
                            <li class="flex items-center gap-3 px-6 py-3">
                                <x-ui.avatar-initial :nama="$anggota->nama" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-stone-900">{{ $anggota->nama }}</span>
                                    <span class="block text-xs text-stone-500">{{ $anggota->no_anggota }}</span>
                                </span>
                                <a href="{{ route('transaksi.setor.create', ['anggota_id' => $anggota->id]) }}" class="text-xs font-medium text-merah hover:text-merah-700 hover:underline">Catat</a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($jumlahBelumSetorWajib > $belumSetorWajib->count())
                        <p class="border-t border-stone-100 px-6 py-3 text-xs text-stone-500">Menampilkan {{ $belumSetorWajib->count() }} dari {{ $jumlahBelumSetorWajib }} anggota.</p>
                    @endif
                @endif

                <p class="border-t border-stone-100 px-6 py-3 text-xs text-stone-400">Informasi saja. Aturan setoran wajib mengikuti kebijakan koperasi.</p>
            </x-ui.card>
        </div>

        {{-- E. Transaksi terbaru --}}
        <x-ui.card padding="none" class="lg:col-span-3">
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Transaksi terbaru</h2>
                <a href="{{ route('transaksi.riwayat') }}" class="inline-flex items-center gap-1 text-sm font-medium text-stone-600 hover:text-stone-900">
                    Lihat semua
                    <x-ui.icon name="chevron-right" class="w-4 h-4" />
                </a>
            </x-slot:header>

            @if ($transaksiTerbaru->isEmpty())
                <x-ui.empty icon="banknotes" title="Belum ada transaksi">
                    <x-ui.button variant="secondary" size="sm" :href="route('transaksi.setor.create')">Catat setoran</x-ui.button>
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Anggota</th>
                                <th>Jenis simpanan</th>
                                <th>Jenis transaksi</th>
                                <th>Tanggal</th>
                                <th class="num">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiTerbaru as $transaksi)
                                @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar-initial :nama="$transaksi->anggota->nama" />
                                            <div class="min-w-0">
                                                <div class="font-medium text-stone-900"><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></div>
                                                <div class="text-xs text-stone-500">{{ $transaksi->anggota->no_anggota }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $transaksi->jenisSimpanan->nama_jenis }}</td>
                                    <td>
                                        @if ($setor)
                                            <x-ui.badge tone="success">Setor</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="danger">Tarik</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                    <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-emerald-700' : 'text-red-700' }}" data-nominal>{{ $setor ? '+' : '-' }}{{ Format::rupiah($transaksi->jumlah) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>

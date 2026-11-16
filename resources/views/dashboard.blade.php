@php
    use App\Models\JenisSimpanan;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    // Dashboard ulang (F2b D): kondisi uang sekarang -> apa yang terjadi -> apa yang perlu dikerjakan.

    // Tanda nominal selalu ASCII (F1 §0, F2b §0.4): +Rp … / -Rp …; nol ditulis Rp 0 tanpa tanda.
    $bertanda = fn ($nilai) => (float) $nilai > 0 ? '+'.Format::rupiah($nilai) : ((float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah(0));
    $rupiahMinus = fn ($nilai) => (float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah($nilai);
    $nadaTanda = fn ($nilai) => (float) $nilai > 0 ? 'setor' : ((float) $nilai < 0 ? 'tarik' : 'nol');

    $chipNada = ['setor' => 'bg-setor-50 text-setor', 'tarik' => 'bg-tarik-50 text-tarik', 'nol' => 'bg-stone-100 text-stone-500'];

    // Warna jenis simpanan untuk sebaran simpanan (sama dengan objek WARNA di arus-simpanan.js, F2c H).
    $warnaJenis = [
        JenisSimpanan::POKOK => 'bg-arang',
        JenisSimpanan::WAJIB => 'bg-emas',
        JenisSimpanan::SUKARELA => 'bg-slate-400', // #94A3B8
    ];

    // D.1 kalimat status, dipilih berurutan (teks F2c F).
    if ($jumlahBelumSetorWajib > 0) {
        $kalimatStatus = 'Ada '.$jumlahBelumSetorWajib.' anggota yang belum setor wajib bulan ini.';
    } elseif ($aktivitasHariIni['jumlah_transaksi'] > 0) {
        $kalimatStatus = 'Hari ini sudah ada '.$aktivitasHariIni['jumlah_transaksi'].' transaksi.';
    } else {
        $kalimatStatus = 'Belum ada transaksi hari ini.';
    }

    $bersihBulanIni = round($aktivitasBulanIni['total_setor'] - $aktivitasBulanIni['total_tarik'], 2);

    // F2c B (revisi K3): rincian kartu ringkasan. Tanda ASCII; positif setor, negatif tarik, nol stone-900.
    $warnaBersih = ['setor' => 'text-setor', 'tarik' => 'text-tarik', 'nol' => 'text-stone-900'];
    $bersihHariIni = round($aktivitasHariIni['total_setor'] - $aktivitasHariIni['total_tarik'], 2);
    $enamBulan = $grafikArus['periode']['enam_bulan'];
    $bulanLalu = fn (array $nilai) => (float) ($nilai[count($nilai) - 2] ?? 0);

    $kartuRingkasan = [
        [
            'jangkar' => 'total',
            'label' => 'Total simpanan',
            'nilai' => Format::rupiah($totalSaldo),
            'rincian' => [
                ['label' => 'Bersih bulan ini', 'nilai' => $bertanda($bersihBulanIni), 'warna' => $warnaBersih[$nadaTanda($bersihBulanIni)]],
                ['label' => 'Bersih hari ini', 'nilai' => $bertanda($bersihHariIni), 'warna' => $warnaBersih[$nadaTanda($bersihHariIni)]],
            ],
        ],
        [
            'jangkar' => 'anggota',
            'label' => 'Anggota aktif',
            'nilai' => $ringkasanAnggota['aktif'],
            'rincian' => [
                ['label' => 'Baru bulan ini', 'nilai' => $ringkasanAnggota['baru_bulan_ini']],
                ['label' => 'Nonaktif', 'nilai' => $ringkasanAnggota['nonaktif']],
            ],
        ],
        [
            'jangkar' => 'setor',
            'label' => 'Setoran bulan ini',
            'nilai' => Format::rupiah($aktivitasBulanIni['total_setor']),
            'rincian' => [
                ['label' => 'Hari ini', 'nilai' => Format::rupiah($aktivitasHariIni['total_setor'])],
                ['label' => 'Bulan lalu', 'nilai' => Format::rupiah($bulanLalu($enamBulan['setor']))],
            ],
        ],
        [
            'jangkar' => 'tarik',
            'label' => 'Penarikan bulan ini',
            'nilai' => Format::rupiah($aktivitasBulanIni['total_tarik']),
            'rincian' => [
                ['label' => 'Hari ini', 'nilai' => Format::rupiah($aktivitasHariIni['total_tarik'])],
                ['label' => 'Bulan lalu', 'nilai' => Format::rupiah($bulanLalu($enamBulan['tarik']))],
            ],
        ],
    ];

    // D.3 kepala grafik: dirender untuk periode default; JS memperbaruinya saat periode/tampilan berganti.
    $periodeAwal = $grafikArus['periode'][$grafikArus['default']];
    $ringkasanAwal = $periodeAwal['ringkasan'];
    $tabPeriode = ['minggu' => '1 Mgg', 'bulan' => '1 Bln', 'tiga_bulan' => '3 Bln', 'enam_bulan' => '6 Bln', 'tahun' => '1 Thn', 'lima_tahun' => '5 Thn'];
    $pilihanTampilan = ['garis' => 'Garis', 'batang' => 'Batang', 'saldo' => 'Saldo', 'gabungan' => 'Gabungan'];

    // F2c C.1 chip perubahan dan C.3 nama kolom pertama tabel; teks yang sama ada di arus-simpanan.js.
    $dalamPeriode = ['minggu' => 'dalam 7 hari', 'bulan' => 'dalam 30 hari', 'tiga_bulan' => 'dalam 3 bulan', 'enam_bulan' => 'dalam 6 bulan', 'tahun' => 'dalam setahun', 'lima_tahun' => 'dalam 5 tahun'];
    $teksChip = fn ($nilai, string $periode) => (float) $nilai == 0 ? 'Tidak berubah' : $bertanda($nilai).' '.$dalamPeriode[$periode];
    // F2c D.3 bilah filter transaksi terbaru.
    $segmenTransaksi = ['' => 'Semua', TransaksiSimpanan::SETOR => 'Setoran', TransaksiSimpanan::TARIK => 'Penarikan'];
    $pilihanRentang = ['' => 'Semua waktu', 'hari_ini' => 'Hari ini', '7_hari' => '7 hari', '30_hari' => '30 hari', 'bulan_ini' => 'Bulan ini'];
    $adaFilterTransaksi = collect($filterTransaksi)->filter(fn ($nilai) => $nilai !== null)->isNotEmpty();

    $kolomPertama = ['minggu' => 'Tanggal', 'bulan' => 'Tanggal', 'tiga_bulan' => 'Minggu', 'enam_bulan' => 'Bulan', 'tahun' => 'Bulan', 'lima_tahun' => 'Kuartal'];
@endphp

<x-app-layout>
    {{-- D.1 Kepala halaman --}}
    <x-ui.page-header :title="Format::sapaan(now()).', '.auth()->user()->nama" :subtitle="$kalimatStatus">
        <x-slot:actions>
            <div data-aksi-cepat class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" :href="route('anggota.create')" icon="user-plus">Tambah anggota</x-ui.button>
                <x-ui.button variant="secondary" :href="route('transaksi.tarik.create')" icon="arrow-up-tray">Catat penarikan</x-ui.button>
                <x-ui.button :href="route('transaksi.setor.create')" icon="arrow-down-tray">Catat setoran</x-ui.button>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-12 items-stretch gap-6">
        {{-- F2c B (revisi K): empat kartu ringkasan putih sama lebar, rincian dua baris. --}}
        <div class="col-span-12 grid grid-cols-1 items-stretch gap-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kartuRingkasan as $kartu)
                <x-ui.stat class="h-full" :label="$kartu['label']" :value="$kartu['nilai']" :rincian="$kartu['rincian']" :data-kartu-ringkasan="$kartu['jangkar']" />
            @endforeach
        </div>

        {{-- F2c C.1–C.3 Arus simpanan: kepala tetap (4 baris), kaki berisi tombol tabel dan tautan laporan. --}}
        <x-ui.card class="col-span-12 flex h-full flex-col xl:col-span-8" padding="none" :header-border="false" data-kartu-arus>
            <x-slot:header>
                <div class="w-full min-w-0">
                    <p class="text-sm text-stone-500">Saldo simpanan</p>
                    <div class="mt-1 flex items-center gap-3">
                        <p data-arus-saldo class="whitespace-nowrap text-[28px] font-semibold leading-tight text-stone-900 tabular-nums">{{ $rupiahMinus($ringkasanAwal['saldo_akhir']) }}</p>
                        <span data-arus-chip class="inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums {{ $chipNada[$nadaTanda($ringkasanAwal['bersih'])] }}">{{ $teksChip($ringkasanAwal['bersih'], $grafikArus['default']) }}</span>
                    </div>
                    <ul class="mt-2 flex items-center gap-x-4 whitespace-nowrap text-sm text-stone-500">
                        <li class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-setor" aria-hidden="true"></span>Setoran <span data-arus-setor class="tabular-nums">{{ Format::rupiah($ringkasanAwal['total_setor']) }}</span></li>
                        <li class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-tarik" aria-hidden="true"></span>Penarikan <span data-arus-tarik class="tabular-nums">{{ Format::rupiah($ringkasanAwal['total_tarik']) }}</span></li>
                        <li data-arus-legenda-saldo hidden class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[#C8930E]" aria-hidden="true"></span>Saldo</li>
                    </ul>

                    @if ($grafikArus['ada_data'])
                        <div data-baris-kontrol class="mt-4 flex items-center justify-between gap-4">
                            <div role="group" aria-label="Periode" class="inline-flex gap-1">
                                @foreach ($tabPeriode as $kunci => $teks)
                                    @php $aktif = $kunci === $grafikArus['default']; @endphp
                                    <button type="button" data-periode="{{ $kunci }}" aria-pressed="{{ $aktif ? 'true' : 'false' }}"
                                            class="h-8 rounded-full px-3 text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30 {{ $aktif ? 'bg-arang text-white' : 'text-stone-500 hover:bg-stone-100' }}">{{ $teks }}</button>
                                @endforeach
                            </div>

                            <div class="relative" x-data="{ buka: false }"
                                 x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                                 x-on:click.outside="buka = false">
                                <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                             aria-label="Tampilan grafik" aria-haspopup="menu" aria-controls="menu-tampilan" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                    <span data-tampilan-label>Garis</span>
                                    <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                                </x-ui.button>
                                <div id="menu-tampilan" role="menu" aria-label="Tampilan grafik" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                                     class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                                    @foreach ($pilihanTampilan as $kunci => $teks)
                                        <button type="button" role="menuitemradio" data-tampilan="{{ $kunci }}" aria-checked="{{ $kunci === 'garis' ? 'true' : 'false' }}"
                                                x-on:click="buka = false; $refs.pemicu.focus()"
                                                class="group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900">
                                            {{ $teks }}
                                            <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </x-slot:header>

            <div class="flex h-full flex-col" x-data="{ tabel: false }">
                @if ($grafikArus['ada_data'])
                    <div class="px-6 pb-5 pt-3">
                        <div class="relative h-[320px]">
                            <canvas id="grafik-arus" role="img" aria-label="Grafik arus setoran, penarikan, dan saldo simpanan"></canvas>
                        </div>
                    </div>
                    <script type="application/json" id="data-grafik">@json($grafikArus)</script>
                @else
                    <x-ui.empty class="flex-1 justify-center" icon="chart-bar" title="Belum ada transaksi" description="Grafik akan terisi setelah ada setoran atau penarikan." />
                @endif

                <div data-kaki-grafik class="mt-auto flex items-center justify-between gap-4 border-t border-stone-200 px-6 py-3">
                    @if ($grafikArus['ada_data'])
                        <button type="button" data-tombol-tabel x-on:click="tabel = ! tabel" aria-controls="tabel-arus"
                                x-bind:aria-expanded="tabel.toString()" aria-expanded="false"
                                class="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-stone-600 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">
                            <span x-text="tabel ? 'Sembunyikan tabel' : 'Tampilkan tabel'">Tampilkan tabel</span>
                            <x-ui.icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="tabel && 'rotate-180'" />
                        </button>
                    @else
                        <span></span>
                    @endif
                    <a href="{{ route('laporan.keseluruhan') }}" data-tautan-rekap class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-stone-900">
                        Laporan lengkap
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>

                @if ($grafikArus['ada_data'])
                    <div id="tabel-arus" x-cloak x-show="tabel" class="max-h-[280px] overflow-y-auto border-t border-stone-200">
                        <table class="tabel">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th data-arus-kolom-pertama>{{ $kolomPertama[$grafikArus['default']] }}</th>
                                    <th class="num">Setoran</th>
                                    <th class="num">Penarikan</th>
                                    <th class="num">Saldo akhir</th>
                                </tr>
                            </thead>
                            <tbody data-arus-tabel>
                                {{-- Baris terbaru di atas (C.3). --}}
                                @foreach (array_reverse($periodeAwal['label_lengkap'], true) as $i => $label)
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td class="num">{{ Format::rupiah($periodeAwal['setor'][$i]) }}</td>
                                        <td class="num">{{ Format::rupiah($periodeAwal['tarik'][$i]) }}</td>
                                        <td class="num">{{ $rupiahMinus($periodeAwal['saldo'][$i]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </x-ui.card>

        {{-- F2c C.4 Sebaran simpanan --}}
        <x-ui.card class="col-span-12 h-full xl:col-span-4" data-kartu-komposisi>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Sebaran simpanan</h2>
            </x-slot:header>

            @if ((float) $totalSaldo > 0)
                <div class="relative mx-auto h-[220px] w-[220px]">
                    <canvas id="grafik-komposisi" role="img" aria-label="Sebaran simpanan per jenis"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-xl font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($totalSaldo) }}</span>
                        <span class="text-xs text-stone-500">Total simpanan</span>
                    </div>
                </div>
                <script type="application/json" id="data-komposisi">@json($rekapSaldo)</script>
            @else
                <div class="mx-auto flex h-[220px] w-[220px] items-center justify-center rounded-full border-[18px] border-stone-100" aria-hidden="true"></div>
                <p class="mt-4 text-center text-sm text-stone-500">Belum ada simpanan.</p>
            @endif

            <ul class="mt-6 divide-y divide-stone-200 text-sm" data-daftar-komposisi>
                @foreach ($rekapSaldo as $rekap)
                    <li class="flex items-center gap-3 py-2.5">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $warnaJenis[$rekap['nama_jenis']] ?? 'bg-stone-300' }}" aria-hidden="true"></span>
                        <span class="flex-1 text-stone-700">{{ str_replace('Simpanan ', '', $rekap['nama_jenis']) }}</span>
                        @if ((float) $totalSaldo > 0)
                            <span class="text-stone-500 tabular-nums">{{ number_format(max(0, (float) $rekap['saldo']) / (float) $totalSaldo * 100, 1, ',', '.') }}%</span>
                        @endif
                        <span class="w-32 text-right font-medium text-stone-900 tabular-nums">{{ Format::rupiah($rekap['saldo']) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        {{-- D.5 Transaksi terbaru, dengan bilah filter (F2c D.3) --}}
        <x-ui.card id="transaksi-terbaru" class="col-span-12 h-full scroll-mt-8 xl:col-span-8" padding="none">
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Transaksi terbaru</h2>
                <a href="{{ route('transaksi.riwayat') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Lihat semua</a>
            </x-slot:header>

            <form method="GET" action="{{ route('dashboard') }}#transaksi-terbaru" data-filter-transaksi
                  class="flex flex-wrap items-center gap-2 border-b border-stone-200 px-6 py-3">
                <div class="relative min-w-[200px] flex-1">
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="cari" value="{{ $filterTransaksi['cari'] }}" placeholder="Cari nama atau no. anggota" aria-label="Cari nama atau no. anggota"
                           class="block h-9 w-full rounded-full border border-stone-200 bg-stone-50 pl-9 pr-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-arang/40 focus:bg-white focus:ring-2 focus:ring-arang/15">
                </div>

                <fieldset class="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5">
                    <legend class="sr-only">Jenis transaksi</legend>
                    @foreach ($segmenTransaksi as $nilai => $teks)
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_transaksi" value="{{ $nilai }}" class="peer sr-only" @checked(($filterTransaksi['jenis_transaksi'] ?? '') === $nilai)>
                            <span class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium text-stone-500 transition-colors hover:text-stone-900 peer-checked:bg-arang peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
                        </label>
                    @endforeach
                </fieldset>

                <select name="jenis_simpanan_id" aria-label="Jenis simpanan"
                        class="h-9 rounded-full border border-stone-200 bg-stone-50 pl-3 pr-8 text-sm text-stone-700 focus:border-arang/40 focus:ring-2 focus:ring-arang/15">
                    <option value="">Semua jenis</option>
                    @foreach ($jenisSimpanan as $jenis)
                        <option value="{{ $jenis->id }}" @selected($filterTransaksi['jenis_simpanan_id'] === (string) $jenis->id)>{{ str_replace('Simpanan ', '', $jenis->nama_jenis) }}</option>
                    @endforeach
                </select>

                <select name="rentang" aria-label="Rentang waktu"
                        class="h-9 rounded-full border border-stone-200 bg-stone-50 pl-3 pr-8 text-sm text-stone-700 focus:border-arang/40 focus:ring-2 focus:ring-arang/15">
                    @foreach ($pilihanRentang as $nilai => $teks)
                        <option value="{{ $nilai }}" @selected(($filterTransaksi['rentang'] ?? '') === $nilai)>{{ $teks }}</option>
                    @endforeach
                </select>

                <x-ui.button variant="secondary" size="sm" type="submit">Terapkan</x-ui.button>
                @if ($adaFilterTransaksi)
                    <a href="{{ route('dashboard') }}#transaksi-terbaru" data-reset-filter class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
                @endif
            </form>

            @if ($adaFilterTransaksi && $transaksiTerbaru->isNotEmpty())
                <div data-info-filter class="flex items-center justify-between gap-4 border-b border-stone-200 px-6 py-2.5 text-xs text-stone-500">
                    <span>Menampilkan 10 transaksi terbaru sesuai filter.</span>
                    <a href="{{ route('transaksi.riwayat', array_filter($filterTransaksi, fn ($nilai) => $nilai !== null)) }}" data-lihat-semua-filter class="font-medium text-stone-600 hover:text-stone-900">Lihat semua</a>
                </div>
            @endif

            @if ($transaksiTerbaru->isEmpty() && $adaFilterTransaksi)
                <x-ui.empty icon="magnifying-glass" title="Tidak ada transaksi yang cocok.">
                    <a href="{{ route('dashboard') }}#transaksi-terbaru" data-reset-filter class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
                </x-ui.empty>
            @elseif ($transaksiTerbaru->isEmpty())
                <x-ui.empty icon="banknotes" title="Belum ada transaksi">
                    <x-ui.button variant="secondary" size="sm" :href="route('transaksi.setor.create')">Catat setoran</x-ui.button>
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Transaksi</th>
                                <th>Anggota</th>
                                <th>Tanggal</th>
                                <th class="num">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiTerbaru as $transaksi)
                                @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                                <tr data-transaksi="{{ $setor ? 'setor' : 'tarik' }}">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $setor ? 'bg-setor-50 text-setor' : 'bg-tarik-50 text-tarik' }}">
                                                <x-ui.icon :name="$setor ? 'arrow-down-tray' : 'arrow-up-tray'" class="w-5 h-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-stone-900">{{ $setor ? 'Setor' : 'Tarik' }}</div>
                                                <div class="text-xs text-stone-500">{{ $transaksi->jenisSimpanan->nama_jenis }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-stone-900"><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></div>
                                        <div class="font-mono text-xs text-stone-500">{{ $transaksi->anggota->no_anggota }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <div>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</div>
                                        <div class="text-xs text-stone-400">oleh {{ $transaksi->staff->nama }}</div>
                                    </td>
                                    <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-setor' : 'text-tarik' }}" data-nominal>{{ $setor ? '+' : '-' }}{{ Format::rupiah($transaksi->jumlah) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        {{-- F2c E Belum setor wajib --}}
        <x-ui.card class="col-span-12 flex h-full flex-col xl:col-span-4" padding="none" data-kartu-tindak-lanjut>
            <x-slot:header>
                <div class="flex min-w-0 items-baseline gap-2">
                    <h2 class="text-base font-semibold text-stone-900">Belum setor wajib</h2>
                    <span class="text-sm text-stone-500" data-bulan-berjalan>Bulan {{ now()->translatedFormat('F') }}</span>
                </div>
                <x-ui.badge tone="warning" class="shrink-0" data-jumlah-belum-setor>{{ $jumlahBelumSetorWajib }} anggota</x-ui.badge>
            </x-slot:header>

            @if ($belumSetorWajib->isEmpty())
                <div class="flex flex-col items-center px-6 py-10 text-center">
                    <x-ui.icon name="check-circle" class="w-10 h-10 text-setor" />
                    <p class="mt-3 text-sm text-stone-600">Semua anggota aktif sudah setor wajib bulan ini.</p>
                </div>
            @else
                <ul class="divide-y divide-stone-200" data-belum-setor-wajib>
                    @foreach ($belumSetorWajib as $anggota)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <x-ui.avatar-initial :nama="$anggota->nama" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-stone-900">{{ $anggota->nama }}</span>
                                <span class="block font-mono text-xs text-stone-500">{{ $anggota->no_anggota }}</span>
                            </span>
                            <x-ui.button variant="secondary" size="sm" :href="route('transaksi.setor.create', ['anggota_id' => $anggota->id])">Catat</x-ui.button>
                        </li>
                    @endforeach
                </ul>
                @if ($jumlahBelumSetorWajib > $belumSetorWajib->count())
                    <div class="border-t border-stone-200 px-6 py-3">
                        <a href="{{ route('transaksi.setor.create') }}" data-lihat-lainnya class="text-sm font-medium text-stone-600 hover:text-stone-900">Lihat {{ $jumlahBelumSetorWajib - $belumSetorWajib->count() }} lainnya</a>
                    </div>
                @endif
            @endif

            <p class="mt-auto border-t border-stone-200 px-6 py-3 text-xs text-stone-400">Daftar ini hanya pengingat.</p>
        </x-ui.card>
    </div>
</x-app-layout>

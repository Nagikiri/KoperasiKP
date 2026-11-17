@php
    use App\Models\JenisSimpanan;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    // Dashboard ulang (F2b D): kondisi uang sekarang -> apa yang terjadi -> apa yang perlu dikerjakan.

    // Tanda nominal selalu ASCII (F1 §0, F2b §0.4): +Rp … / -Rp …; nol ditulis Rp 0 tanpa tanda.
    $bertanda = fn ($nilai) => (float) $nilai > 0 ? '+'.Format::rupiah($nilai) : ((float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah(0));
    $rupiahMinus = fn ($nilai) => (float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah($nilai);
    $nadaTanda = fn ($nilai) => (float) $nilai > 0 ? 'setor' : ((float) $nilai < 0 ? 'tarik' : 'nol');

    // Warna jenis simpanan untuk sebaran simpanan (sama dengan objek WARNA di arus-simpanan.js, F2c H).
    $warnaJenis = [
        JenisSimpanan::POKOK => 'bg-arang',
        JenisSimpanan::WAJIB => 'bg-emas',
        JenisSimpanan::SUKARELA => 'bg-slate-400', // #94A3B8
    ];

    // D.1 kalimat status, dipilih berurutan (teks F2c F).
    if ($jumlahBelumSetorWajib > 0) {
        // F2d I.5: tetap dari belum setor wajib BULAN INI; "{n} anggota" menjadi tautan ke kartu.
        $kalimatStatus = new \Illuminate\Support\HtmlString(
            'Ada <a href="#belum-setor-wajib" data-tautan-belum-wajib class="font-medium text-stone-900 underline decoration-stone-300 underline-offset-2 hover:decoration-stone-500">'
            .e($jumlahBelumSetorWajib.' anggota').'</a> yang belum setor wajib bulan ini'
            .($jumlahBelumSetorWajibDuaBulan > 0 ? e(', '.$jumlahBelumSetorWajibDuaBulan.' di antaranya juga belum bulan lalu') : '')
            .'.'
        );
    } elseif ($aktivitasHariIni['jumlah_transaksi'] > 0) {
        $kalimatStatus = 'Hari ini sudah ada '.$aktivitasHariIni['jumlah_transaksi'].' transaksi.';
    } else {
        $kalimatStatus = 'Belum ada transaksi hari ini.';
    }

    $bersihBulanIni = round($aktivitasBulanIni['total_setor'] - $aktivitasBulanIni['total_tarik'], 2);

    // F2c B (revisi K3), F2d A.3: rincian kartu ringkasan. Tanda ASCII; positif naik, negatif turun, nol stone-900.
    $warnaBersih = ['setor' => 'text-naik', 'tarik' => 'text-turun', 'nol' => 'text-stone-900'];
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

    // F2d C.3, I.1 tiga kotak arus: label, nominal, jumlah transaksi. Teks yang sama ada di arus-simpanan.js.
    $teksPeriode = ['minggu' => '7 hari', 'bulan' => '30 hari', 'tiga_bulan' => '3 bulan', 'enam_bulan' => '6 bulan', 'tahun' => '1 tahun', 'lima_tahun' => '5 tahun'];
    $kotakArus = [
        ['kunci' => 'setor', 'nama' => 'Setoran', 'titik' => 'bg-setor', 'nilai' => Format::rupiah($ringkasanAwal['total_setor']), 'warna' => 'text-stone-900',
            'jumlah' => $ringkasanAwal['jumlah_setor']],
        ['kunci' => 'tarik', 'nama' => 'Penarikan', 'titik' => 'bg-tarik', 'nilai' => Format::rupiah($ringkasanAwal['total_tarik']), 'warna' => 'text-stone-900',
            'jumlah' => $ringkasanAwal['jumlah_tarik']],
        // Titik emas = warna garis saldo (H.3.7). Jumlah transaksi = setor + tarik (I.1.2).
        ['kunci' => 'selisih', 'nama' => 'Selisih bersih', 'titik' => 'bg-[#C8930E]', 'nilai' => $bertanda($ringkasanAwal['bersih']), 'warna' => $warnaBersih[$nadaTanda($ringkasanAwal['bersih'])],
            'jumlah' => $ringkasanAwal['jumlah_setor'] + $ringkasanAwal['jumlah_tarik']],
    ];
    // I.1.3: nominal dipecah "Rp" | angka, supaya hanya boleh turun baris setelah "Rp".
    $pecahRp = fn (string $teks) => explode(' ', $teks, 2) + [1 => ''];
    $persamaan = [
        ['kunci' => 'saldo_awal', 'label' => 'Saldo awal', 'nilai' => $rupiahMinus($ringkasanAwal['saldo_awal']), 'operator' => '+'],
        ['kunci' => 'total_setor', 'label' => 'Setoran', 'nilai' => Format::rupiah($ringkasanAwal['total_setor']), 'operator' => '-'],
        ['kunci' => 'total_tarik', 'label' => 'Penarikan', 'nilai' => Format::rupiah($ringkasanAwal['total_tarik']), 'operator' => '='],
        ['kunci' => 'saldo_akhir', 'label' => 'Saldo akhir', 'nilai' => $rupiahMinus($ringkasanAwal['saldo_akhir']), 'operator' => null],
    ];
    // C.3 (F2c) nama kolom pertama tabel; teks yang sama ada di arus-simpanan.js.
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
                    <p data-arus-diperbarui class="mt-0.5 text-xs text-stone-400">Diperbarui {{ now()->translatedFormat('j M, H.i') }}</p>
                    <p data-arus-saldo class="mt-1 whitespace-nowrap text-[28px] font-semibold leading-tight text-stone-900 tabular-nums">{{ $rupiahMinus($ringkasanAwal['saldo_akhir']) }}</p>

                    {{-- F2d I.1: tiga kotak sama tinggi; isi = label, nominal (tanpa terpotong/ellipsis), jumlah transaksi. --}}
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach ($kotakArus as $kotak)
                            @php [$rp, $angka] = $pecahRp($kotak['nilai']); @endphp
                            <div data-arus-kotak="{{ $kotak['kunci'] }}" class="flex min-w-0 flex-col rounded-xl border border-stone-200 bg-stone-50/60 p-4 [container-type:inline-size]">
                                <p class="flex items-center gap-1.5 truncate text-xs text-stone-500">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $kotak['titik'] }}" aria-hidden="true"></span>
                                    <span class="truncate">{{ $kotak['nama'] }} · <span data-arus-periode-teks>{{ $teksPeriode[$grafikArus['default']] }}</span></span>
                                </p>
                                <p data-arus-nilai class="mt-1.5 text-[clamp(16px,9cqi,24px)] font-semibold leading-tight tabular-nums {{ $kotak['warna'] }}"><span data-nilai-rp class="whitespace-nowrap">{{ $rp }}</span> <span data-nilai-angka class="whitespace-nowrap">{{ $angka }}</span></p>
                                <p data-arus-jumlah class="mt-1 text-xs text-stone-500 tabular-nums">{{ $kotak['jumlah'] }} transaksi</p>
                            </div>
                        @endforeach
                    </div>

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
                    <div class="px-6 pb-4 pt-3">
                        <div class="relative h-[320px]">
                            <canvas id="grafik-arus" role="img" aria-label="Grafik arus setoran, penarikan, dan saldo simpanan"></canvas>
                        </div>
                    </div>
                    <script type="application/json" id="data-grafik">@json($grafikArus)</script>

                    {{-- F2d C.5 baris persamaan: saldo awal + setoran - penarikan = saldo akhir. --}}
                    <div data-arus-persamaan class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 px-6 pb-5">
                        <div class="grid flex-1 grid-cols-2 gap-x-4 gap-y-3 sm:flex sm:flex-none sm:items-end sm:gap-3">
                            @foreach ($persamaan as $suku)
                                <div class="min-w-0">
                                    <p class="text-xs text-stone-500">{{ $suku['label'] }}</p>
                                    <p data-persamaan="{{ $suku['kunci'] }}" class="whitespace-nowrap text-sm font-semibold text-stone-900 tabular-nums">{{ $suku['nilai'] }}</p>
                                </div>
                                @if ($suku['operator'])
                                    <span class="hidden pb-0.5 text-sm text-stone-400 sm:block" aria-hidden="true">{{ $suku['operator'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
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

            @php
                // F2d D: porsi per jenis (urut id). Seri terbesar/terkecil -> id lebih kecil (H.3.8).
                $adaSaldo = (float) $totalSaldo > 0;
                $porsi = $rekapSaldo->values()->map(fn ($r) => [
                    'nama' => str_replace('Simpanan ', '', $r['nama_jenis']),
                    'saldo' => max(0.0, (float) $r['saldo']),
                    'persen' => $adaSaldo ? max(0.0, (float) $r['saldo']) / (float) $totalSaldo * 100 : 0.0,
                ])->all();
                $persenTeks = fn (float $nilai) => number_format($nilai, 1, ',', '.').'%';
                $indeksTerbesar = null;
                foreach ($porsi as $i => $p) {
                    if ($indeksTerbesar === null || $p['saldo'] > $porsi[$indeksTerbesar]['saldo']) $indeksTerbesar = $i;
                }
            @endphp

            @if ($adaSaldo)
                <div class="relative mx-auto h-[220px] w-[220px]">
                    <canvas id="grafik-komposisi" role="img" aria-label="Sebaran simpanan per jenis"></canvas>
                    {{-- D.1: tengah donat = jenis terbesar; berganti saat irisan/baris disorot. --}}
                    <div data-komposisi-tengah data-default-persen="{{ $persenTeks($porsi[$indeksTerbesar]['persen']) }}" data-default-nama="{{ $porsi[$indeksTerbesar]['nama'] }}"
                         class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center" aria-live="polite">
                        <span data-tengah-persen class="text-2xl font-semibold text-stone-900 tabular-nums">{{ $persenTeks($porsi[$indeksTerbesar]['persen']) }}</span>
                        <span data-tengah-nama class="text-xs text-stone-500">{{ $porsi[$indeksTerbesar]['nama'] }}</span>
                    </div>
                </div>
                <script type="application/json" id="data-komposisi">@json($rekapSaldo)</script>
            @else
                <div class="mx-auto flex h-[220px] w-[220px] items-center justify-center rounded-full border-[18px] border-stone-100" aria-hidden="true"></div>
                <p class="mt-4 text-center text-sm text-stone-500">Belum ada simpanan.</p>
            @endif

            <ul class="mt-6 divide-y divide-stone-200 text-sm" data-daftar-komposisi>
                @foreach ($rekapSaldo->values() as $i => $rekap)
                    <li class="flex items-center gap-3 rounded-md py-2.5 transition-colors" data-komposisi-jenis="{{ $i }}">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $warnaJenis[$rekap['nama_jenis']] ?? 'bg-stone-300' }}" aria-hidden="true"></span>
                        <span class="flex-1 text-stone-700">{{ $porsi[$i]['nama'] }}</span>
                        @if ($adaSaldo)
                            <span class="text-stone-500 tabular-nums">{{ $persenTeks($porsi[$i]['persen']) }}</span>
                        @endif
                        <span class="w-32 text-right font-medium text-stone-900 tabular-nums">{{ Format::rupiah($rekap['saldo']) }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- D.3 baris Total, sejajar kolom daftar. --}}
            <div data-komposisi-total class="flex items-center gap-3 border-t border-stone-300 py-2.5 text-sm font-semibold text-stone-900">
                <span class="h-2.5 w-2.5 shrink-0" aria-hidden="true"></span>
                <span class="flex-1">Total</span>
                @if ($adaSaldo)
                    <span class="tabular-nums">100%</span>
                @endif
                <span class="w-32 text-right tabular-nums">{{ Format::rupiah($totalSaldo) }}</span>
            </div>

            {{-- F2d I.3 ringkasan: semua nilai dari $ringkasanSebaran (database), tanpa nama jenis/angka yang diketik di sini.
                 Kelompok 1 dijumlahkan = baris Total. --}}
            @if ($adaSaldo)
                <div data-komposisi-ringkasan class="mt-3 text-sm">
                    <dl class="space-y-2" data-ringkasan-kelompok="saldo">
                        <div class="flex items-center justify-between gap-3" data-ringkasan-baris="bisa_ditarik">
                            <dt class="text-stone-500">Bisa ditarik anggota</dt>
                            <dd class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanSebaran['bisa_ditarik']) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3" data-ringkasan-baris="tertahan">
                            <dt class="text-stone-500">Tertahan (pokok + wajib)</dt>
                            <dd class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanSebaran['tertahan']) }}</dd>
                        </div>
                        @if ($ringkasanSebaran['milik_nonaktif'] > 0)
                            <div class="flex items-center justify-between gap-3" data-ringkasan-baris="milik_nonaktif">
                                <dt class="text-stone-500">Milik anggota nonaktif</dt>
                                <dd class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanSebaran['milik_nonaktif']) }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="my-3 border-t border-dashed border-stone-200"></div>

                    <dl class="space-y-2" data-ringkasan-kelompok="anggota">
                        @if ($ringkasanSebaran['rata_rata_aktif'] !== null)
                            <div class="flex items-center justify-between gap-3" data-ringkasan-baris="rata_rata_aktif">
                                <dt class="text-stone-500">Rata-rata per anggota aktif</dt>
                                <dd class="font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanSebaran['rata_rata_aktif']) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-start justify-between gap-3" data-ringkasan-baris="wajib_masuk_bulan_ini">
                            <dt class="text-stone-500">Wajib masuk bulan ini</dt>
                            <dd class="text-right">
                                <span class="block font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($ringkasanSebaran['wajib_masuk_bulan_ini']) }}</span>
                                <span data-wajib-sudah class="block text-xs text-stone-500 tabular-nums">{{ $ringkasanSebaran['sudah_setor_wajib'] }} dari {{ $ringkasanSebaran['anggota_aktif'] }} anggota</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            @endif
        </x-ui.card>

        {{-- D.5 Transaksi terbaru, dengan bilah filter (F2c D.3) --}}
        <x-ui.card id="transaksi-terbaru" class="col-span-12 h-full scroll-mt-8 xl:col-span-8" padding="none">
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Transaksi terbaru</h2>
                <a href="{{ route('transaksi.riwayat') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Lihat semua</a>
            </x-slot:header>

            @php
                // F2d E.2: dua dropdown menu (pola sama dengan dropdown Tampilan grafik), nilai lewat input tersembunyi.
                $dropdownFilter = [
                    ['nama' => 'rentang', 'label' => 'Rentang waktu', 'opsi' => $pilihanRentang, 'nilai' => $filterTransaksi['rentang'] ?? ''],
                    ['nama' => 'jenis_simpanan_id', 'label' => 'Jenis simpanan',
                        'opsi' => ['' => 'Semua jenis'] + $jenisSimpanan->mapWithKeys(fn ($jenis) => [(string) $jenis->id => str_replace('Simpanan ', '', $jenis->nama_jenis)])->all(),
                        'nilai' => $filterTransaksi['jenis_simpanan_id'] ?? ''],
                ];
            @endphp
            {{-- F2d E.1–E.4, H.4.4: segmen dan dropdown langsung terkirim; kolom cari hanya saat Enter. --}}
            <form method="GET" action="{{ route('dashboard') }}#transaksi-terbaru" data-filter-transaksi x-data
                  class="space-y-3 border-b border-stone-200 px-6 py-3">
                <div class="relative">
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="cari" value="{{ $filterTransaksi['cari'] }}" placeholder="Cari nama atau no. anggota" aria-label="Cari nama atau no. anggota"
                           x-init="if ($el.value) { $el.focus({ preventScroll: true }); $el.setSelectionRange($el.value.length, $el.value.length) }"
                           class="block h-9 w-full rounded-full border border-stone-200 bg-stone-50 pl-9 pr-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-arang/40 focus:bg-white focus:ring-2 focus:ring-arang/15">
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <fieldset class="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5">
                        <legend class="sr-only">Jenis transaksi</legend>
                        @foreach ($segmenTransaksi as $nilai => $teks)
                            <label class="cursor-pointer">
                                <input type="radio" name="jenis_transaksi" value="{{ $nilai }}" class="peer sr-only" @checked(($filterTransaksi['jenis_transaksi'] ?? '') === $nilai)
                                       x-on:change="$el.form.requestSubmit()">
                                <span class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium text-stone-500 transition-colors hover:text-stone-900 peer-checked:bg-arang peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($dropdownFilter as $dropdown)
                            <div class="relative" data-dropdown-filter="{{ $dropdown['nama'] }}" x-data="{ buka: false }"
                                 x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                                 x-on:click.outside="buka = false">
                                <input type="hidden" name="{{ $dropdown['nama'] }}" value="{{ $dropdown['nilai'] }}" x-ref="nilai">
                                <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                             :aria-label="$dropdown['label']" aria-haspopup="menu" aria-controls="menu-{{ $dropdown['nama'] }}" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                    <span data-dropdown-label>{{ $dropdown['opsi'][$dropdown['nilai']] ?? reset($dropdown['opsi']) }}</span>
                                    <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                                </x-ui.button>
                                <div id="menu-{{ $dropdown['nama'] }}" role="menu" aria-label="{{ $dropdown['label'] }}" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                                     class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                                    @foreach ($dropdown['opsi'] as $nilai => $teks)
                                        <button type="button" role="menuitemradio" data-nilai="{{ $nilai }}" aria-checked="{{ (string) $nilai === (string) $dropdown['nilai'] ? 'true' : 'false' }}"
                                                x-on:click="buka = false; $refs.nilai.value = @js((string) $nilai); $el.closest('form').requestSubmit()"
                                                class="group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900">
                                            {{ $teks }}
                                            <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if ($adaFilterTransaksi)
                            <a href="{{ route('dashboard') }}#transaksi-terbaru" data-reset-filter class="px-1 text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
                        @endif
                    </div>
                </div>

                {{-- F2d I.4: pilihan kartu belum setor wajib ikut terbawa. --}}
                @if ($wajib['pilihan'] !== '')
                    <input type="hidden" name="wajib" value="{{ $wajib['pilihan'] }}">
                @endif

                {{-- E.3: tetap ada untuk keyboard dan pembaca layar. --}}
                <button type="submit" class="sr-only">Terapkan</button>
            </form>

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

            {{-- F2d E.6: kaki kartu, hanya jika transaksi yang cocok > 10. Riwayat menerima filter yang sama (H.2.1). --}}
            @if ($jumlahTransaksiCocok > 10)
                <div class="border-t border-stone-200 px-6 py-3">
                    <a href="{{ route('transaksi.riwayat', array_filter($filterTransaksi, fn ($nilai) => $nilai !== null)) }}" data-lihat-semua-transaksi
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-stone-900">
                        Lihat semua {{ $jumlahTransaksiCocok }} transaksi
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            @endif
        </x-ui.card>

        @php
            // F2d I.4: satu daftar sesuai pilihan `wajib` (server). F.3: baris yang tampil sebelum "Lihat semua";
            // dihitung dari tinggi kartu Transaksi terbaru (10 baris) di sebelahnya, lihat Laporan.
            $batasBarisWajib = 12;
            $kunciDaftar = $wajib['pilihan'] === '' ? 'ini' : $wajib['pilihan'];
            $opsiWajibAtas = ['' => 'Bulan ini', 'lalu' => 'Bulan lalu'];
            $opsiTunggakan = ['tunggakan_3' => 'Tunggakan 3 bulan', 'tunggakan_6' => 'Tunggakan 6 bulan', 'tunggakan_12' => 'Tunggakan 12 bulan'];
            $pilihBulanAktif = isset($wajib['opsi_bulan'][$wajib['pilihan']]);
            $kelasOpsi = 'group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900';
        @endphp
        {{-- F2c E, F2d F/I.4: Belum setor wajib --}}
        <x-ui.card id="belum-setor-wajib" class="col-span-12 flex h-full scroll-mt-8 flex-col xl:col-span-4" padding="none" data-kartu-tindak-lanjut
                   x-data="{ semua: false, tinggi: null }">
            <x-slot:header>
                {{-- F.1: baris 1 judul; baris 2 pilihan (kiri) dan badge (kanan). --}}
                <div class="w-full min-w-0">
                    <h2 class="text-base font-semibold text-stone-900">Belum setor wajib</h2>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        {{-- I.4.1: dropdown menu (pola sama dengan filter transaksi), dikirim lewat `wajib`. Filter transaksi ikut terbawa. --}}
                        <form method="GET" action="{{ route('dashboard') }}#belum-setor-wajib" data-pilih-wajib class="relative"
                              x-data="{ buka: false, bulanLain: {{ $pilihBulanAktif ? 'true' : 'false' }} }"
                              x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                              x-on:click.outside="buka = false">
                            @foreach (array_filter($filterTransaksi, fn ($nilai) => $nilai !== null) as $kunci => $nilai)
                                <input type="hidden" name="{{ $kunci }}" value="{{ $nilai }}">
                            @endforeach
                            <input type="hidden" name="wajib" value="{{ $wajib['pilihan'] }}" x-ref="nilai">

                            <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                         aria-label="Pilihan belum setor wajib" aria-haspopup="menu" aria-controls="menu-wajib" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                <span data-wajib-label>{{ $wajib['label'] }}</span>
                                <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                            </x-ui.button>

                            <div id="menu-wajib" role="menu" aria-label="Pilihan belum setor wajib" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                                 class="absolute left-0 z-10 mt-2 w-52 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                                @foreach ($opsiWajibAtas as $nilai => $teks)
                                    <button type="button" role="menuitemradio" data-wajib-opsi="{{ $nilai }}" aria-checked="{{ $wajib['pilihan'] === (string) $nilai ? 'true' : 'false' }}"
                                            x-on:click="buka = false; $refs.nilai.value = @js((string) $nilai); $el.closest('form').requestSubmit()" class="{{ $kelasOpsi }}">
                                        {{ $teks }}
                                        <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                    </button>
                                @endforeach

                                <button type="button" role="menuitem" data-wajib-pilih-bulan aria-controls="menu-wajib-bulan" x-bind:aria-expanded="bulanLain.toString()" aria-expanded="{{ $pilihBulanAktif ? 'true' : 'false' }}"
                                        x-on:click="bulanLain = ! bulanLain"
                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none">
                                    Pilih bulan…
                                    <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-400 transition-transform" x-bind:class="bulanLain && 'rotate-180'" />
                                </button>
                                <div id="menu-wajib-bulan" role="group" aria-label="Pilih bulan" x-show="bulanLain" @if (! $pilihBulanAktif) x-cloak @endif class="max-h-48 overflow-y-auto bg-stone-50/60">
                                    @foreach ($wajib['opsi_bulan'] as $nilai => $teks)
                                        <button type="button" role="menuitemradio" data-wajib-opsi="{{ $nilai }}" aria-checked="{{ $wajib['pilihan'] === (string) $nilai ? 'true' : 'false' }}"
                                                x-on:click="buka = false; $refs.nilai.value = @js((string) $nilai); $el.closest('form').requestSubmit()" class="{{ $kelasOpsi }} pl-6">
                                            {{ $teks }}
                                            <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                        </button>
                                    @endforeach
                                </div>

                                <div role="separator" class="my-1 border-t border-stone-200"></div>

                                @foreach ($opsiTunggakan as $nilai => $teks)
                                    <button type="button" role="menuitemradio" data-wajib-opsi="{{ $nilai }}" aria-checked="{{ $wajib['pilihan'] === $nilai ? 'true' : 'false' }}"
                                            x-on:click="buka = false; $refs.nilai.value = @js($nilai); $el.closest('form').requestSubmit()" class="{{ $kelasOpsi }}">
                                        {{ $teks }}
                                        <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                    </button>
                                @endforeach
                            </div>
                        </form>
                        <x-ui.badge tone="brand" class="shrink-0 tabular-nums" data-jumlah-belum-setor>{{ $wajib['jumlah'] }} anggota</x-ui.badge>
                    </div>
                </div>
            </x-slot:header>

            <div data-daftar-wajib="{{ $kunciDaftar }}" data-mode-wajib="{{ $wajib['mode'] }}">
                @if ($wajib['daftar']->isEmpty())
                    <div class="flex flex-col items-center px-6 py-10 text-center">
                        <x-ui.icon name="check-circle" class="w-10 h-10 text-setor" />
                        <p class="mt-3 text-sm text-stone-600">{{ $wajib['kosong'] }}</p>
                    </div>
                @else
                    {{-- F.4: saat dibuka penuh, tinggi daftar dikunci ke tinggi sebelumnya dan isinya digulir. --}}
                    <div x-ref="daftar" x-bind:class="semua && 'overflow-y-auto'" x-bind:style="semua && tinggi ? { maxHeight: tinggi + 'px' } : {}">
                        <ul class="divide-y divide-stone-200" data-belum-setor-wajib>
                            @foreach ($wajib['daftar'] as $i => $anggota)
                                <li class="flex items-center gap-3 px-6 py-3" @if ($i >= $batasBarisWajib) x-show="semua" x-cloak data-baris-lebih @endif>
                                    <x-ui.avatar-initial :nama="$anggota->nama" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-stone-900">{{ $anggota->nama }}</span>
                                        <span class="block font-mono text-xs text-stone-500">{{ $anggota->no_anggota }}</span>
                                        @if ($wajib['mode'] === 'tunggakan')
                                            <span data-bolong class="block text-xs text-emas-700">bolong {{ $anggota->bolong }} bulan</span>
                                        @endif
                                    </span>
                                    <x-ui.button variant="secondary" size="sm" :href="route('transaksi.setor.create', ['anggota_id' => $anggota->id])">Catat</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                        @if ($wajib['jumlah'] > $wajib['daftar']->count())
                            <p data-sisa-wajib x-show="semua" x-cloak class="px-6 py-2 text-xs text-stone-400">dan {{ $wajib['jumlah'] - $wajib['daftar']->count() }} lainnya</p>
                        @endif
                    </div>

                    @if ($wajib['jumlah'] > min($batasBarisWajib, $wajib['daftar']->count()))
                        <div class="border-t border-stone-200 px-6 py-3">
                            <button type="button" data-lihat-semua-wajib aria-expanded="false" x-bind:aria-expanded="semua.toString()"
                                    x-on:click="if (! semua) { tinggi = $refs.daftar.offsetHeight } semua = ! semua"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">
                                <span x-text="semua ? 'Tampilkan lebih sedikit' : @js('Lihat semua '.$wajib['jumlah'].' anggota')">Lihat semua {{ $wajib['jumlah'] }} anggota</span>
                                <x-ui.icon name="arrow-right" class="h-4 w-4" x-show="! semua" />
                            </button>
                        </div>
                    @endif
                @endif
            </div>
        </x-ui.card>
    </div>
</x-app-layout>

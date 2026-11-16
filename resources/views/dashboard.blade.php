@php
    use App\Models\JenisSimpanan;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    // Dashboard ulang (F2b D): kondisi uang sekarang -> apa yang terjadi -> apa yang perlu dikerjakan.

    // Tanda nominal selalu ASCII (F1 §0, F2b §0.4): +Rp … / -Rp …; nol ditulis Rp 0 tanpa tanda.
    $bertanda = fn ($nilai) => (float) $nilai > 0 ? '+'.Format::rupiah($nilai) : ((float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah(0));
    $rupiahMinus = fn ($nilai) => (float) $nilai < 0 ? '-'.Format::rupiah(abs((float) $nilai)) : Format::rupiah($nilai);
    $nadaTanda = fn ($nilai) => (float) $nilai > 0 ? 'setor' : ((float) $nilai < 0 ? 'tarik' : 'nol');

    $teksNada = ['setor' => 'text-setor', 'tarik' => 'text-tarik', 'nol' => 'text-stone-500'];
    $chipNada = ['setor' => 'bg-setor-50 text-setor', 'tarik' => 'bg-tarik-50 text-tarik', 'nol' => 'bg-stone-100 text-stone-500'];

    // Warna jenis simpanan untuk komposisi saldo (sama dengan objek WARNA di arus-simpanan.js).
    $warnaJenis = [
        JenisSimpanan::POKOK => 'bg-arang',
        JenisSimpanan::WAJIB => 'bg-emas',
        JenisSimpanan::SUKARELA => 'bg-stone-400',
    ];

    // D.1 kalimat status, dipilih berurutan.
    if ($jumlahBelumSetorWajib > 0) {
        $kalimatStatus = $jumlahBelumSetorWajib.' anggota belum setor wajib bulan ini.';
    } elseif ($aktivitasHariIni['jumlah_transaksi'] > 0) {
        $kalimatStatus = $aktivitasHariIni['jumlah_transaksi'].' transaksi tercatat hari ini.';
    } else {
        $kalimatStatus = 'Belum ada transaksi hari ini.';
    }

    $bersihBulanIni = round($aktivitasBulanIni['total_setor'] - $aktivitasBulanIni['total_tarik'], 2);

    // D.3 kepala grafik: dirender untuk periode default; JS memperbaruinya saat periode/tampilan berganti.
    $periodeAwal = $grafikArus['periode'][$grafikArus['default']];
    $ringkasanAwal = $periodeAwal['ringkasan'];
    $tabPeriode = ['minggu' => '1 Mgg', 'bulan' => '1 Bln', 'tiga_bulan' => '3 Bln', 'enam_bulan' => '6 Bln', 'tahun' => '1 Thn', 'lima_tahun' => '5 Thn'];
    $pilihanTampilan = ['garis' => 'Garis', 'batang' => 'Batang', 'saldo' => 'Saldo', 'kombinasi' => 'Kombinasi'];
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
        {{-- D.2 Empat kartu KPI --}}
        <x-ui.stat class="col-span-12 h-full sm:col-span-6 xl:col-span-3" label="Total saldo simpanan" :value="Format::rupiah($totalSaldo)" icon="banknotes">
            Bersih bulan ini <span data-bersih-bulan-ini class="font-medium tabular-nums {{ $teksNada[$nadaTanda($bersihBulanIni)] }}">{{ $bertanda($bersihBulanIni) }}</span>
        </x-ui.stat>

        <x-ui.stat class="col-span-12 h-full sm:col-span-6 xl:col-span-3" label="Anggota aktif" :value="$ringkasanAnggota['aktif']" icon="users">
            {{ $ringkasanAnggota['baru_bulan_ini'] }} anggota baru bulan ini &middot; {{ $ringkasanAnggota['nonaktif'] }} nonaktif
        </x-ui.stat>

        <x-ui.stat class="col-span-12 h-full sm:col-span-6 xl:col-span-3" label="Setor bulan ini" :value="Format::rupiah($aktivitasBulanIni['total_setor'])" icon="arrow-down-tray" icon-tone="setor">
            Hari ini <span class="tabular-nums">{{ Format::rupiah($aktivitasHariIni['total_setor']) }}</span>
        </x-ui.stat>

        <x-ui.stat class="col-span-12 h-full sm:col-span-6 xl:col-span-3" label="Tarik bulan ini" :value="Format::rupiah($aktivitasBulanIni['total_tarik'])" icon="arrow-up-tray" icon-tone="tarik">
            Hari ini <span class="tabular-nums">{{ Format::rupiah($aktivitasHariIni['total_tarik']) }}</span>
        </x-ui.stat>

        {{-- D.3 Arus simpanan --}}
        <x-ui.card class="col-span-12 flex h-full flex-col xl:col-span-8" :header-border="false" data-kartu-arus>
            <x-slot:header>
                <div class="flex w-full flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-stone-500">Saldo simpanan &middot; <span data-arus-nama>{{ $periodeAwal['nama'] }}</span></p>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <p data-arus-saldo class="text-[28px] font-semibold leading-tight text-stone-900 tabular-nums">{{ $rupiahMinus($ringkasanAwal['saldo_akhir']) }}</p>
                            <span data-arus-chip class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium tabular-nums {{ $chipNada[$nadaTanda($ringkasanAwal['bersih'])] }}">{{ $bertanda($ringkasanAwal['bersih']) }}</span>
                        </div>
                        <ul class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-stone-500">
                            <li class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-setor" aria-hidden="true"></span>Setor <span data-arus-setor class="tabular-nums">{{ Format::rupiah($ringkasanAwal['total_setor']) }}</span></li>
                            <li class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-tarik" aria-hidden="true"></span>Tarik <span data-arus-tarik class="tabular-nums">{{ Format::rupiah($ringkasanAwal['total_tarik']) }}</span></li>
                            <li data-arus-legenda-saldo hidden class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emas" aria-hidden="true"></span>Saldo</li>
                        </ul>
                    </div>

                    @if ($grafikArus['ada_data'])
                        <div class="flex flex-col items-end gap-2">
                            <div role="group" aria-label="Periode" class="inline-flex flex-wrap gap-1">
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
                                             aria-haspopup="menu" aria-controls="menu-tampilan" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                    Tampilan: <span data-tampilan-label>Garis</span>
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

            <div class="flex flex-1 flex-col">
                @if ($grafikArus['ada_data'])
                    <div class="relative h-[300px]">
                        <canvas id="grafik-arus" role="img" aria-label="Grafik arus setoran, penarikan, dan saldo simpanan"></canvas>
                    </div>
                    <script type="application/json" id="data-grafik">@json($grafikArus)</script>

                    <details class="mt-4 text-sm">
                        <summary class="cursor-pointer text-stone-600 hover:text-stone-900">Lihat angka</summary>
                        <div class="mt-3 max-h-72 overflow-y-auto">
                            <table class="tabel">
                                <thead>
                                    <tr>
                                        <th>Periode</th>
                                        <th class="num">Setor</th>
                                        <th class="num">Tarik</th>
                                        <th class="num">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody data-arus-tabel>
                                    @foreach ($periodeAwal['label_lengkap'] as $i => $label)
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
                    </details>
                @else
                    <x-ui.empty class="flex-1 justify-center" icon="chart-bar" title="Belum ada transaksi" description="Grafik muncul setelah transaksi pertama dicatat." />
                @endif

                <div class="mt-auto pt-4">
                    <a href="{{ route('laporan.keseluruhan') }}" data-tautan-rekap class="text-sm font-medium text-stone-600 hover:text-stone-900">Lihat rekap lengkap &rarr;</a>
                </div>
            </div>
        </x-ui.card>

        {{-- D.4 Komposisi saldo --}}
        <x-ui.card class="col-span-12 h-full xl:col-span-4" data-kartu-komposisi>
            <x-slot:header>
                <div>
                    <h2 class="text-base font-semibold text-stone-900">Komposisi saldo</h2>
                    <p class="text-xs text-stone-500">Per jenis simpanan</p>
                </div>
            </x-slot:header>

            @if ((float) $totalSaldo > 0)
                <div class="relative mx-auto h-[200px] w-[200px]">
                    <canvas id="grafik-komposisi" role="img" aria-label="Komposisi saldo per jenis simpanan"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                        <span class="text-base font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($totalSaldo) }}</span>
                        <span class="text-xs text-stone-500">Total saldo</span>
                    </div>
                </div>
                <script type="application/json" id="data-komposisi">@json($rekapSaldo)</script>
            @else
                <div class="mx-auto flex h-[200px] w-[200px] items-center justify-center rounded-full border-[18px] border-stone-100" aria-hidden="true"></div>
                <p class="mt-4 text-center text-sm text-stone-500">Belum ada saldo tercatat.</p>
            @endif

            <ul class="mt-6 space-y-3 text-sm" data-daftar-komposisi>
                @foreach ($rekapSaldo as $rekap)
                    <li class="flex items-center gap-3">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $warnaJenis[$rekap['nama_jenis']] ?? 'bg-stone-300' }}" aria-hidden="true"></span>
                        <span class="flex-1 text-stone-700">{{ $rekap['nama_jenis'] }}</span>
                        @if ((float) $totalSaldo > 0)
                            <span class="text-stone-500 tabular-nums">{{ number_format(max(0, (float) $rekap['saldo']) / (float) $totalSaldo * 100, 1, ',', '.') }}%</span>
                        @endif
                        <span class="w-32 text-right font-medium text-stone-900 tabular-nums">{{ Format::rupiah($rekap['saldo']) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        {{-- D.5 Transaksi terbaru --}}
        <x-ui.card class="col-span-12 h-full xl:col-span-8" padding="none">
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Transaksi terbaru</h2>
                <a href="{{ route('transaksi.riwayat') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Lihat semua &rarr;</a>
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

        {{-- D.6 Perlu ditindaklanjuti --}}
        <x-ui.card class="col-span-12 flex h-full flex-col xl:col-span-4" padding="none" data-kartu-tindak-lanjut>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Perlu ditindaklanjuti</h2>
            </x-slot:header>

            <div class="flex items-center justify-between gap-3 px-6 pt-4 pb-2">
                <h3 class="text-sm font-medium text-stone-700">Belum setor wajib bulan ini</h3>
                <x-ui.badge tone="warning">{{ $jumlahBelumSetorWajib }}</x-ui.badge>
            </div>

            @if ($belumSetorWajib->isEmpty())
                <div class="flex flex-col items-center px-6 py-10 text-center">
                    <x-ui.icon name="check-circle" class="w-10 h-10 text-setor" />
                    <p class="mt-3 text-sm text-stone-600">Semua anggota aktif sudah setor wajib bulan ini.</p>
                </div>
            @else
                <ul class="divide-y divide-stone-100" data-belum-setor-wajib>
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
                    <p class="border-t border-stone-100 px-6 py-3 text-xs text-stone-500">Menampilkan {{ $belumSetorWajib->count() }} dari {{ $jumlahBelumSetorWajib }} anggota.</p>
                @endif
            @endif

            <p class="mt-auto border-t border-stone-100 px-6 py-3 text-xs text-stone-400">Informasi saja. Aturan setoran wajib mengikuti kebijakan koperasi.</p>
        </x-ui.card>
    </div>
</x-app-layout>

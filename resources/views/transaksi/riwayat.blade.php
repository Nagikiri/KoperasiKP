@php
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;
    use Illuminate\Support\Str;

    // F4 H.3.4: pilihan bilah filter. Kunci kosong = tanpa filter. Satu peta label; tampilan selalu lewat ?? cadangan.
    $segmen = ['' => 'Semua', TransaksiSimpanan::SETOR => 'Setoran', TransaksiSimpanan::TARIK => 'Penarikan'];
    $pilihanJenis = ['' => 'Semua jenis'] + $jenisSimpanan->mapWithKeys(fn ($jenis) => [(string) $jenis->id => str_replace('Simpanan ', '', $jenis->nama_jenis)])->all();
    $pilihanWaktu = [
        '' => 'Semua waktu',
        'hari_ini' => 'Hari ini',
        '7_hari' => '7 hari',
        '30_hari' => '30 hari',
        'bulan_ini' => 'Bulan ini',
        'bulan_lalu' => 'Bulan lalu',
        '3_bulan' => '3 bulan terakhir',
        'tahun_ini' => 'Tahun ini',
    ];
    $labelWaktu = $pilihanWaktu + ['kustom' => 'Pilih tanggal…'];
    $rentangAktif = $filter['rentang'] ?? '';
    $jenisAktif = $filter['jenis_simpanan_id'] ?? '';
    $adaFilter = collect($filter)->contains(fn ($nilai) => $nilai !== null);
    $urlReset = route('transaksi.riwayat');
    $kelasOpsi = 'group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900';
@endphp

<x-app-layout>
    <x-ui.page-header title="Riwayat transaksi" subtitle="Semua setoran dan penarikan simpanan.">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('transaksi.setor.create')" icon="arrow-down-tray">Catat setoran</x-ui.button>
            <x-ui.button variant="secondary" :href="route('transaksi.tarik.create')" icon="arrow-up-tray">Catat penarikan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none" data-kartu-riwayat-transaksi>
        {{--
            H.3.4: semua pilihan langsung terkirim (cari saat Enter, select anggota saat berubah); "Pilih tanggal…" baru
            terkirim setelah kedua tanggal terisi. Input kosong di-disabled sebelum kirim supaya URL dan tautan paginasi
            bersih, lalu diaktifkan lagi saat halaman dipulihkan dari cache Back/Forward (H.6.5). Form tidak membawa
            `page`, jadi filter baru selalu kembali ke halaman 1.
        --}}
        <form method="GET" action="{{ route('transaksi.riwayat') }}" data-filter-riwayat
              x-data="{
                  rentang: @js($rentangAktif),
                  kirim() { this.$nextTick(() => this.$root.requestSubmit()) },
                  cekTanggal() { if (this.$refs.dari.value && this.$refs.sampai.value) this.kirim() },
                  bersihkan() {
                      this.$root.querySelectorAll('input[name], select[name]').forEach((el) => {
                          if (! el.disabled && el.value === '') { el.disabled = true; el.setAttribute('data-dimatikan-kosong', '') }
                      })
                  },
                  pulihkan() {
                      this.$root.querySelectorAll('[data-dimatikan-kosong]').forEach((el) => { el.disabled = false; el.removeAttribute('data-dimatikan-kosong') })
                  },
              }"
              x-on:submit="bersihkan()"
              x-on:pageshow.window="pulihkan()"
              class="space-y-3 border-b border-stone-200 px-6 py-4">
            <div class="grid gap-2 md:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="relative">
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    <x-ui.input type="search" name="cari" value="{{ $filter['cari'] }}" placeholder="Cari nama atau no. anggota" aria-label="Cari nama atau no. anggota" :ikon-kiri="true" />
                </div>
                <x-ui.select name="anggota_id" aria-label="Anggota" x-on:change="kirim()" data-pilih-anggota>
                    <option value="">Semua anggota</option>
                    @foreach ($anggotaList as $anggota)
                        <option value="{{ $anggota->id }}" @selected($filter['anggota_id'] === (string) $anggota->id)>{{ $anggota->no_anggota }} - {{ Format::namaAnggota($anggota->nama, $anggota->status) }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <fieldset class="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5">
                    <legend class="sr-only">Jenis transaksi</legend>
                    @foreach ($segmen as $nilai => $teks)
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_transaksi" value="{{ $nilai }}" class="peer sr-only" @checked(($filter['jenis_transaksi'] ?? '') === $nilai)
                                   x-on:change="kirim()">
                            <span class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium text-stone-500 transition-colors hover:text-stone-900 peer-checked:bg-arang peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Dropdown jenis simpanan (salinan pola detail anggota, jangkar sendiri). --}}
                    <div class="relative" data-dropdown-riwayat-transaksi="jenis_simpanan_id" x-data="{ buka: false }"
                         x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                         x-on:click.outside="buka = false">
                        <input type="hidden" name="jenis_simpanan_id" value="{{ $jenisAktif }}" x-ref="nilai">
                        <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                     aria-label="Jenis simpanan" aria-haspopup="menu" aria-controls="menu-riwayat-transaksi-jenis" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                            <span data-dropdown-label>{{ $pilihanJenis[$jenisAktif] ?? 'Semua jenis' }}</span>
                            <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                        </x-ui.button>
                        <div id="menu-riwayat-transaksi-jenis" role="menu" aria-label="Jenis simpanan" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                             class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                            @foreach ($pilihanJenis as $nilai => $teks)
                                <button type="button" role="menuitemradio" data-nilai="{{ $nilai }}" aria-checked="{{ (string) $nilai === $jenisAktif ? 'true' : 'false' }}"
                                        x-on:click="buka = false; $refs.nilai.value = @js((string) $nilai); kirim()" class="{{ $kelasOpsi }}">
                                    {{ $teks }}
                                    <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Dropdown waktu: "Pilih tanggal…" dipisah garis dan tidak mengirim form. --}}
                    <div class="relative" data-dropdown-riwayat-transaksi="rentang" x-data="{ buka: false, label: @js($labelWaktu) }"
                         x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                         x-on:click.outside="buka = false">
                        <input type="hidden" name="rentang" value="{{ $rentangAktif }}" x-bind:value="rentang">
                        <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                     aria-label="Rentang waktu" aria-haspopup="menu" aria-controls="menu-riwayat-transaksi-waktu" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                            <span data-dropdown-label x-text="label[rentang] ?? label['']">{{ $labelWaktu[$rentangAktif] ?? 'Semua waktu' }}</span>
                            <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                        </x-ui.button>
                        <div id="menu-riwayat-transaksi-waktu" role="menu" aria-label="Rentang waktu" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                             class="absolute right-0 z-10 mt-2 w-44 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                            @foreach ($pilihanWaktu as $nilai => $teks)
                                <button type="button" role="menuitemradio" data-nilai="{{ $nilai }}" aria-checked="{{ (string) $nilai === $rentangAktif ? 'true' : 'false' }}"
                                        x-bind:aria-checked="(rentang === @js((string) $nilai)).toString()"
                                        x-on:click="buka = false; rentang = @js((string) $nilai); kirim()" class="{{ $kelasOpsi }}">
                                    {{ $teks }}
                                    <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                </button>
                            @endforeach
                            <div role="separator" class="my-1 border-t border-stone-200"></div>
                            <button type="button" role="menuitemradio" data-nilai="kustom" aria-checked="{{ $rentangAktif === 'kustom' ? 'true' : 'false' }}"
                                    x-bind:aria-checked="(rentang === 'kustom').toString()"
                                    x-on:click="buka = false; rentang = 'kustom'; $nextTick(() => $refs.dari.focus())" class="{{ $kelasOpsi }}">
                                Pilih tanggal…
                                <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>

                    @if ($adaFilter)
                        <a href="{{ $urlReset }}" data-reset-riwayat-transaksi class="px-1 text-sm font-medium text-stone-600 hover:text-stone-900">Reset Filter</a>
                    @endif
                </div>
            </div>

            {{-- Rentang kustom: kolom disabled saat rentang bukan kustom supaya tidak ikut terkirim. --}}
            <div data-rentang-tanggal x-show="rentang === 'kustom'" @if ($rentangAktif !== 'kustom') x-cloak @endif
                 class="flex flex-wrap items-center justify-end gap-3">
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    Dari
                    <span class="block w-44">
                        <x-ui.input type="date" name="dari" value="{{ $filter['dari'] }}" x-ref="dari"
                                    x-bind:disabled="rentang !== 'kustom'" x-on:change="cekTanggal()" :disabled="$rentangAktif !== 'kustom'" />
                    </span>
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    Sampai
                    <span class="block w-44">
                        <x-ui.input type="date" name="sampai" value="{{ $filter['sampai'] }}" x-ref="sampai"
                                    x-bind:disabled="rentang !== 'kustom'" x-on:change="cekTanggal()" :disabled="$rentangAktif !== 'kustom'" />
                    </span>
                </label>
            </div>

            <button type="submit" class="sr-only">Terapkan Filter</button>
        </form>

        {{-- H.3.6: ringkasan dari total paginator dan satu query agregasi dengan filter yang sama. --}}
        <div class="border-b border-stone-200 px-6 py-2.5 text-sm text-stone-500" data-ringkasan-riwayat-transaksi>
            <span class="font-medium text-stone-900 tabular-nums" data-ringkasan-jumlah>{{ $riwayat->total() }} transaksi</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            Setor <span class="font-medium text-setor tabular-nums" data-ringkasan-setor>{{ Format::rupiah($ringkasan['total_setor']) }}</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            Tarik <span class="font-medium text-tarik tabular-nums" data-ringkasan-tarik>{{ Format::rupiah($ringkasan['total_tarik']) }}</span>
        </div>

        @if ($riwayat->isEmpty())
            @if ($adaFilter)
                <x-ui.empty icon="funnel" title="Tidak ada transaksi yang cocok dengan filter." data-kosong-filter>
                    <a href="{{ $urlReset }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset Filter</a>
                </x-ui.empty>
            @else
                <x-ui.empty icon="banknotes" title="Belum ada transaksi." />
            @endif
        @else
            {{-- H.3.7 / H.6.3–H.6.4: tanpa avatar dan tanpa kolom jenis simpanan terpisah; semua sel rata kiri. --}}
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Anggota</th>
                            <th>Transaksi</th>
                            <th class="num">Jumlah</th>
                            <th class="num" title="Saldo jenis simpanan ini setelah transaksi">Saldo jenis ini</th>
                            <th>Dicatat oleh</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as $transaksi)
                            @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                            <tr data-transaksi="{{ $setor ? 'setor' : 'tarik' }}" data-id-transaksi="{{ $transaksi->id }}">
                                <td class="whitespace-nowrap">{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                <td>
                                    <div class="text-stone-900"><x-nama-anggota :nama="$transaksi->anggota->nama" :status="$transaksi->anggota->status" /></div>
                                    <div class="font-mono text-xs text-stone-500">{{ $transaksi->anggota->no_anggota }}</div>
                                </td>
                                <td><x-ui.jenis-transaksi :jenis="$transaksi->jenis_transaksi" :nama-jenis="$transaksi->jenisSimpanan->nama_jenis" /></td>
                                <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-setor' : 'text-tarik' }}" data-nominal>{{ $setor ? '+' : '-' }}{{ Format::rupiah($transaksi->jumlah) }}</td>
                                <td class="num whitespace-nowrap" data-saldo-jenis-ini>{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                                <td>{{ $transaksi->staff->nama }}</td>
                                <td class="text-stone-500" data-sel-catatan>@if (filled($transaksi->keterangan))<span title="{{ $transaksi->keterangan }}" data-catatan>{{ Str::limit($transaksi->keterangan, 40) }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($riwayat->hasPages())
                <div class="border-t border-stone-200 px-6 py-4">
                    {{ $riwayat->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
</x-app-layout>

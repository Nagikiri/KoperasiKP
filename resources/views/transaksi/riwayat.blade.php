@php
    use App\Http\Controllers\TransaksiSimpananController;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;

    // F4 H.3.4: pilihan bilah filter. Kunci kosong = tanpa filter. Satu peta label; tampilan selalu lewat ?? cadangan.
    $segmen = ['' => 'Semua', TransaksiSimpanan::SETOR => 'Setoran', TransaksiSimpanan::TARIK => 'Penarikan'];
    $pilihanJenis = ['' => 'Semua jenis'] + $jenisSimpanan->mapWithKeys(fn ($jenis) => [(string) $jenis->id => str_replace('Simpanan ', '', $jenis->nama_jenis)])->all();
    $pilihanWaktu = TransaksiSimpananController::LABEL_RENTANG_RIWAYAT;
    $labelWaktu = $pilihanWaktu + ['kustom' => 'Pilih tanggal…'];
    $rentangAktif = $filter['rentang'] ?? '';
    $jenisAktif = $filter['jenis_simpanan_id'] ?? '';
    $adaFilter = collect($filter)->contains(fn ($nilai) => $nilai !== null);
    $urlReset = route('transaksi.riwayat');
    $kelasOpsi = 'group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900';

    // Revisi 2 pasca-F4b: kolom cari gabungan. Opsi teks polos "{no} - {nama}" (+ " (Nonaktif)"), F1 §0.
    $labelAnggota = fn ($anggota) => $anggota->no_anggota.' - '.Format::namaAnggota($anggota->nama, $anggota->status);
    $anggotaFilter = $anggotaList->first(fn ($anggota) => (string) $anggota->id === $filter['anggota_id']);

    // Ringkasan: label periode dari helper bersama halaman dan ekspor (F5 H.5.5).
    $labelPeriode = TransaksiSimpananController::labelPeriodeRiwayat($filter);
    $bersih = round((float) $ringkasan['total_setor'] - (float) $ringkasan['total_tarik'], 2);

    // F5 H.5.9: tautan unduh membawa filter aktif (tanpa page); PDF nonaktif jika melebihi batas baris.
    $queryUnduh = array_filter($filter, fn ($nilai) => $nilai !== null);
    $pdfTerlaluBanyak = $riwayat->total() > TransaksiSimpananController::BATAS_PDF_RIWAYAT;
    $kelasItemUnduh = 'flex w-full items-center gap-2 px-3 py-2 text-left text-sm';
@endphp

<x-app-layout>
    <x-ui.page-header title="Riwayat transaksi" subtitle="Semua setoran dan penarikan simpanan.">
        <x-slot:actions>
            @if ($riwayat->total() > 0)
                <div class="relative" data-unduh-riwayat x-data="{ buka: false }"
                     x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                     x-on:click.outside="buka = false">
                    <x-ui.button variant="secondary" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                 aria-haspopup="menu" aria-controls="menu-unduh-riwayat" aria-expanded="false" x-bind:aria-expanded="buka.toString()">
                        Unduh
                        <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                    </x-ui.button>
                    <div id="menu-unduh-riwayat" role="menu" aria-label="Unduh riwayat" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                         class="absolute right-0 z-30 mt-2 w-56 overflow-hidden rounded-md border border-stone-300 bg-white py-1">
                        <a href="{{ route('transaksi.riwayat.excel', $queryUnduh) }}" role="menuitem" data-unduh="excel"
                           class="{{ $kelasItemUnduh }} text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none">Excel (.xlsx)</a>
                        @if ($pdfTerlaluBanyak)
                            <span role="menuitem" aria-disabled="true" data-unduh="pdf" title="Lebih dari {{ number_format(TransaksiSimpananController::BATAS_PDF_RIWAYAT, 0, ',', '.') }} transaksi, pakai Excel atau persempit filter."
                                  class="{{ $kelasItemUnduh }} cursor-not-allowed text-stone-400">PDF (.pdf)</span>
                        @else
                            <a href="{{ route('transaksi.riwayat.pdf', $queryUnduh) }}" role="menuitem" data-unduh="pdf"
                               class="{{ $kelasItemUnduh }} text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none">PDF (.pdf)</a>
                        @endif
                    </div>
                </div>
            @endif
            <x-ui.button variant="secondary" :href="route('transaksi.setor.create')">Catat setoran</x-ui.button>
            <x-ui.button variant="secondary" :href="route('transaksi.tarik.create')">Catat penarikan</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="none" data-kartu-riwayat-transaksi>
        {{--
            H.3.4: semua pilihan langsung terkirim (cari saat Enter, anggota saat dipilih dari panel kolom cari); "Pilih tanggal…" baru
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
            {{--
                Revisi 2 pasca-F4b: kolom cari gabungan selebar penuh (cari + pilih anggota). Saat difokus/diketik, panel
                menampilkan "Semua anggota" lalu anggota yang cocok (nama/no. anggota, tanpa beda huruf besar/kecil),
                gaya sama dengan panel form setor. Memilih anggota: set anggota_id, kosongkan cari, kirim. Enter tanpa
                opsi tersorot: kirim sebagai cari (perilaku lama) dan anggota_id dihapus. Anggota terpilih tampil di
                dalam isian dengan tombol hapus. Nama input tetap `cari` dan `anggota_id` (kontrak $filter tetap).
            --}}
            <div class="relative" data-pilih-anggota
                 x-data="{
                     buka: false,
                     cari: @js($filter['cari'] ?? ''),
                     sorot: null,
                     cocok(el) {
                         if (el.dataset.opsiAnggota === '') return true;
                         const q = this.cari.trim().toLowerCase();
                         return q === '' || el.dataset.kunci.includes(q);
                     },
                     terlihat() {
                         return [...this.$refs.daftar.querySelectorAll('[data-opsi-anggota]')].filter((el) => this.cocok(el));
                     },
                     tutup() { this.buka = false; this.sorot = null },
                     geser(arah) {
                         this.buka = true;
                         const daftar = this.terlihat();
                         const i = daftar.findIndex((el) => el.dataset.opsiAnggota === this.sorot);
                         const j = i === -1 ? (arah > 0 ? 0 : daftar.length - 1) : Math.min(Math.max(i + arah, 0), daftar.length - 1);
                         this.sorot = daftar[j].dataset.opsiAnggota;
                         this.$nextTick(() => this.$refs.daftar.querySelector('[data-opsi-anggota=&quot;' + this.sorot + '&quot;]')?.scrollIntoView({ block: 'nearest' }));
                     },
                     kirimForm() { this.$nextTick(() => this.$refs.cari.form.requestSubmit()) },
                     pilih(el) {
                         this.$refs.anggota.value = el.dataset.opsiAnggota;
                         this.cari = '';
                         this.tutup();
                         this.kirimForm();
                     },
                     enter(event) {
                         const el = this.buka && this.sorot !== null ? this.terlihat().find((el) => el.dataset.opsiAnggota === this.sorot) : null;
                         if (el) { event.preventDefault(); this.pilih(el); return; }
                         this.$refs.anggota.value = '';
                         this.tutup();
                     },
                     hapusAnggota() { this.$refs.anggota.value = ''; this.kirimForm() },
                 }"
                 x-on:keydown.escape="if (buka) { $event.preventDefault(); $event.stopPropagation(); tutup() }"
                 x-on:click.outside="tutup()">
                <input type="hidden" name="anggota_id" value="{{ $anggotaFilter?->id }}" x-ref="anggota">

                <div class="relative flex h-11 w-full items-center gap-2 rounded-md border border-stone-300 bg-white pl-11 pr-3.5 text-sm focus-within:border-arang focus-within:ring-2 focus-within:ring-arang/20" data-isian-cari>
                    <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                    @if ($anggotaFilter)
                        <span class="inline-flex min-w-0 max-w-[70%] shrink-0 items-center gap-1 rounded-lg bg-stone-100 py-1 pl-2.5 pr-1 text-stone-900">
                            <span class="truncate" data-anggota-terpilih>{{ $labelAnggota($anggotaFilter) }}</span>
                            <button type="button" aria-label="Hapus filter anggota" title="Hapus filter anggota" data-hapus-anggota
                                    x-on:click="hapusAnggota()"
                                    class="rounded-md p-0.5 text-stone-500 hover:bg-stone-200 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30">
                                <x-ui.icon name="x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif
                    <input type="search" name="cari" value="{{ $filter['cari'] }}" x-ref="cari" x-model="cari" autocomplete="off"
                           placeholder="Cari nama atau no. anggota" aria-label="Cari nama atau no. anggota"
                           role="combobox" aria-autocomplete="list" aria-controls="daftar-anggota-riwayat" aria-expanded="false" x-bind:aria-expanded="buka.toString()"
                           x-on:focus="buka = true"
                           x-on:input="buka = true; sorot = null"
                           x-on:keydown.down.prevent="geser(1)"
                           x-on:keydown.up.prevent="geser(-1)"
                           x-on:keydown.enter="enter($event)"
                           class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-stone-900 placeholder:text-stone-400 focus:outline-none focus:ring-0">
                </div>

                <div x-cloak x-show="buka" x-transition.opacity.duration.100ms
                     class="absolute inset-x-0 z-20 mt-2 overflow-hidden rounded-md border border-stone-300 bg-white">
                    <ul id="daftar-anggota-riwayat" role="listbox" aria-label="Daftar anggota" x-ref="daftar" class="max-h-72 overflow-y-auto py-1">
                        @foreach ([null, ...$anggotaList] as $anggota)
                            @php
                                $idOpsi = $anggota ? (string) $anggota->id : '';
                                $teksOpsi = $anggota ? $labelAnggota($anggota) : 'Semua anggota';
                            @endphp
                            <li role="option" data-opsi-anggota="{{ $idOpsi }}"
                                data-kunci="{{ $anggota ? mb_strtolower($anggota->nama.' '.$anggota->no_anggota) : '' }}"
                                aria-selected="{{ $idOpsi === ($filter['anggota_id'] ?? '') ? 'true' : 'false' }}"
                                x-show="cocok($el)"
                                x-on:click="pilih($el)"
                                x-on:mouseenter="sorot = $el.dataset.opsiAnggota"
                                x-bind:class="sorot === $el.dataset.opsiAnggota ? 'bg-stone-100' : ''"
                                class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm text-stone-900">
                                <span class="min-w-0 truncate" data-label-opsi>{{ $teksOpsi }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p x-show="terlihat().length <= 1" x-cloak class="px-3 py-3 text-sm text-stone-500" data-anggota-kosong>Tidak ada anggota yang cocok.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <fieldset class="flex gap-5 border-b border-stone-200" data-segmen-garis>
                    <legend class="sr-only">Jenis transaksi</legend>
                    @foreach ($segmen as $nilai => $teks)
                        <label class="cursor-pointer">
                            <input type="radio" name="jenis_transaksi" value="{{ $nilai }}" class="peer sr-only" @checked(($filter['jenis_transaksi'] ?? '') === $nilai)
                                   x-on:change="kirim()">
                            <span class="-mb-px inline-flex border-b-2 border-transparent pb-2 text-sm text-stone-500 transition-colors hover:text-stone-900 peer-checked:border-arang peer-checked:font-medium peer-checked:text-stone-900 peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
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
                             class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-md border border-stone-300 bg-white py-1">
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
                             class="absolute right-0 z-10 mt-2 w-44 overflow-hidden rounded-md border border-stone-300 bg-white py-1">
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

        {{--
            H.3.6: ringkasan dari total paginator dan satu query agregasi dengan filter yang sama.
            Revisi 2 pasca-F4b: diawali label periode, diakhiri Bersih = setor − tarik (bertanda ASCII, naik/turun).
        --}}
        {{--
            State geser bersama (Alpine): tombol ‹ › di baris ringkasan, bayangan kolom tempel, dan petunjuk geser di tepi
            kanan. ada = tabel lebih lebar dari wadahnya; tergeser = tidak di paling kiri; bisaKanan = belum di paling kanan.
        --}}
        <div data-area-riwayat
             x-data="{
                 ada: false,
                 tergeser: false,
                 bisaKanan: false,
                 ukur() {
                     const el = this.$refs.gulir;
                     if (! el) { this.ada = this.tergeser = this.bisaKanan = false; return; }
                     this.ada = el.scrollWidth > el.clientWidth + 1;
                     this.tergeser = el.scrollLeft > 0;
                     this.bisaKanan = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
                 },
                 geser(arah) { this.$refs.gulir?.scrollBy({ left: arah * 320, behavior: 'smooth' }) },
             }"
             x-init="$nextTick(() => ukur())"
             x-on:resize.window="ukur()"
             x-bind:data-tergeser="tergeser">
        <div class="flex items-center justify-between gap-3 border-b border-stone-200 px-6 py-2.5">
        <p class="text-sm text-stone-500" data-ringkasan-riwayat-transaksi>
            <span class="text-stone-700" data-ringkasan-periode>{{ $labelPeriode }}</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            <span class="font-medium text-stone-900 tabular-nums" data-ringkasan-jumlah>{{ number_format($riwayat->total(), 0, ',', '.') }} transaksi</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            Setor <span class="font-medium text-setor-700 tabular-nums" data-ringkasan-setor>{{ Format::rupiah($ringkasan['total_setor']) }}</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            Tarik <span class="font-medium text-tarik-700 tabular-nums" data-ringkasan-tarik>{{ Format::rupiah($ringkasan['total_tarik']) }}</span>
            <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
            Bersih <span @class([
                'font-medium tabular-nums',
                'text-naik' => $bersih > 0,
                'text-turun' => $bersih < 0,
                'text-stone-900' => $bersih == 0,
            ]) data-ringkasan-bersih><span class="normal-nums">{{ $bersih > 0 ? '+' : ($bersih < 0 ? '-' : '') }}</span>{{ Format::rupiah(abs($bersih)) }}</span>
        </p>
        @if ($riwayat->isNotEmpty())
            {{-- Tombol geser: hanya saat tabel lebih lebar dari wadahnya; nonaktif di ujung masing-masing. --}}
            <div class="flex shrink-0 items-center gap-1" x-cloak x-show="ada">
                <x-ui.button variant="ghost" size="sm" square type="button" data-geser-tabel="kiri"
                             aria-label="Geser tabel ke kiri" title="Geser tabel ke kiri (Shift + gulir mouse juga bisa)"
                             x-on:click="geser(-1)" x-bind:disabled="! tergeser">
                    <x-ui.icon name="chevron-left" class="h-4 w-4 text-stone-500" />
                </x-ui.button>
                <x-ui.button variant="ghost" size="sm" square type="button" data-geser-tabel="kanan"
                             aria-label="Geser tabel ke kanan" title="Geser tabel ke kanan (Shift + gulir mouse juga bisa)"
                             x-on:click="geser(1)" x-bind:disabled="! bisaKanan">
                    <x-ui.icon name="chevron-right" class="h-4 w-4 text-stone-500" />
                </x-ui.button>
            </div>
        @endif
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
            {{--
                Revisi 2 pasca-F4b: lebar kolom mengikuti isi (tanpa table-fixed/colgroup); semua sel satu baris dan
                tampil penuh, kecuali Catatan (maks. 2 baris). Tabel min-w-full w-max bergulir mendatar di dalam kartu.
                Kolom Anggota menempel di kiri (sticky) dengan latar sama dengan baris; saat tabel tergeser, kolom itu
                diberi bayangan tipis di kanan. Bayangan di tepi kanan wadah menandakan tabel masih bisa digeser.
                Header tidak menempel. Kolom uang (.num) rata kanan, kolom lain rata kiri (E).
            --}}
            <div class="relative" data-gulir-riwayat>
                <div class="overflow-x-auto focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-arang/30"
                     tabindex="0" aria-label="Tabel riwayat transaksi, bisa digeser"
                     x-ref="gulir" x-on:scroll.passive="ukur()">
                    {{-- border-separate: garis tiap sel ikut bergeser bersama kolom yang menempel (collapse tidak). --}}
                    <table class="tabel w-max min-w-full border-separate border-spacing-0">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 whitespace-nowrap bg-stone-50 [[data-tergeser]_&]:shadow-[6px_0_8px_-6px_rgba(28,25,23,0.18)]" data-kolom-tempel>Anggota</th>
                                <th class="whitespace-nowrap">Tanggal</th>
                                <th class="whitespace-nowrap">Transaksi</th>
                                <th class="num whitespace-nowrap">Jumlah</th>
                                <th class="num whitespace-nowrap" title="Saldo jenis simpanan ini setelah transaksi tersebut">Saldo setelah transaksi</th>
                                <th class="whitespace-nowrap">Dicatat oleh</th>
                                <th class="min-w-[16rem]">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayat as $transaksi)
                                @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                                <tr class="group" data-transaksi="{{ $setor ? 'setor' : 'tarik' }}" data-id-transaksi="{{ $transaksi->id }}">
                                    <td class="sticky left-0 z-10 whitespace-nowrap bg-white group-hover:bg-stone-50 [[data-tergeser]_&]:shadow-[6px_0_8px_-6px_rgba(28,25,23,0.18)]" data-kolom-tempel>
                                        {{-- Nama bertaut (garis bawah hanya saat hover) + baris no. anggota; nonaktif: "· ● Nonaktif" tanpa pil. Tanpa avatar (R1 C.6). --}}
                                        <x-ui.sel-anggota :anggota="$transaksi->anggota" />
                                    </td>
                                    <td class="whitespace-nowrap" data-sel-tanggal>{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                    <td class="whitespace-nowrap">
                                        {{-- Pil cap (R1 C.5): titik setor/tarik, label, lalu jenis tanpa awalan "Simpanan ". --}}
                                        <x-ui.pil-transaksi :jenis="$transaksi->jenis_transaksi" :nama-jenis="$transaksi->jenisSimpanan->nama_jenis" />
                                    </td>
                                    {{-- Tanda ASCII tanpa angka tabular supaya "-Rp" serapat "+Rp". --}}
                                    <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-setor-700' : 'text-tarik-700' }}" data-nominal><span class="normal-nums">{{ $setor ? '+' : '-' }}</span>{{ Format::rupiah($transaksi->jumlah) }}</td>
                                    <td class="num whitespace-nowrap" data-saldo-jenis-ini>{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                                    <td class="whitespace-nowrap" data-sel-staf>{{ $transaksi->staff->nama }}</td>
                                    <td class="min-w-[16rem] whitespace-normal text-stone-500" data-sel-catatan>@if (filled($transaksi->keterangan))<span class="line-clamp-2 max-w-[22rem] break-words" title="{{ $transaksi->keterangan }}" data-catatan>{{ $transaksi->keterangan }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Petunjuk geser: bayangan tipis di tepi kanan selama tabel masih bisa digeser ke kanan. --}}
                <div x-cloak x-show="bisaKanan" x-transition.opacity aria-hidden="true" data-petunjuk-geser
                     class="pointer-events-none absolute inset-y-0 right-0 z-20 w-4 shadow-[inset_-10px_0_8px_-8px_rgba(28,25,23,0.18)]"></div>
            </div>

            @if ($riwayat->hasPages())
                <div class="border-t border-stone-200 px-6 py-4">
                    {{ $riwayat->links() }}
                </div>
            @endif
        @endif
        </div>
    </x-ui.card>
</x-app-layout>

{{--
    Pilih anggota yang bisa dicari (F4b A.1, revisi pasca-F4b), dipakai form setor, form tarik, dan bilah filter Riwayat.
    - Opsi dirender server; Alpine hanya menyaring, menyorot, dan memilih. Teks opsi dan pemicu satu baris dengan satu
      jenis huruf: `{no_anggota} - {nama}` (+ " (Nonaktif)" lewat Format::namaAnggota(), F1 §0).
    - Nilai dikirim lewat input tersembunyi `anggota_id`.
    - Parameter:
        $daftar  (opsional) koleksi anggota; default $anggotaAktif (form setor/tarik, hanya anggota aktif, BR-05).
        $idAwal  (opsional) id terpilih; default old('anggota_id') yang menang atas ?anggota_id= ($anggotaTerpilih).
        $saldo   (opsional, form tarik) peta { id anggota: saldo sukarela }; tampil rata kanan di tiap opsi.
        $semua   (opsional, tidak dipakai sejak revisi 2 pasca-F4b) true = mode filter: opsi pertama "Semua anggota"
                 (nilai kosong), tanpa label terlihat, tanpa pesan error, jangkar data-pilih-anggota.
        $opsiSemua (opsional, F5 H.3 laporan per anggota) true = label "Anggota" dan pesan error anggota_id tetap tampil,
                 opsi pertama "Semua anggota" (nilai kosong). Memilih tidak mengirim form (laporan memakai tombol Tampilkan).
    - Setelah memilih, elemen ini mengirim event `anggota-dipilih` (detail.id, detail.berubah) yang dibaca
      resources/js/transaksi-tarik.js dan bilah filter Riwayat.
    Jangkar test: data-pilih-anggota-cari, tiap opsi data-opsi-anggota="{id}", teks pemicu data-anggota-terpilih.
--}}
@php
    use App\Models\Anggota;
    use App\Support\Format;

    $daftar ??= $anggotaAktif;
    $saldo ??= null;
    $semua ??= false;
    $opsiSemua = $semua || ($opsiSemua ?? false);
    $idAwal = (string) ($idAwal ?? old('anggota_id', $anggotaTerpilih ?? null));
    $terpilih = $daftar->first(fn ($anggota) => (string) $anggota->id === $idAwal);
    $salah = ! $semua && $errors->has('anggota_id');
    // Daftar form setor/tarik dimuat tanpa kolom status (sudah disaring aktif di controller), jadi status kosong = aktif.
    $labelOpsi = fn ($anggota) => $anggota->no_anggota.' - '.Format::namaAnggota($anggota->nama, $anggota->status ?? Anggota::STATUS_AKTIF);
    $kosong = $opsiSemua ? 'Semua anggota' : 'Pilih anggota';
    $labelTerpilih = $terpilih ? $labelOpsi($terpilih) : $kosong;
@endphp

<div class="relative" data-pilih-anggota-cari @if ($semua) data-pilih-anggota @endif
     x-data="{
         buka: false,
         cari: '',
         id: @js($terpilih ? (string) $terpilih->id : ''),
         label: @js($labelTerpilih),
         kosong: @js($kosong),
         semua: @js($opsiSemua),
         sorot: '',
         cocok(el) {
             const q = this.cari.trim().toLowerCase();
             return q === '' || el.dataset.kunci.includes(q);
         },
         terlihat() {
             return [...this.$refs.daftar.querySelectorAll('[data-opsi-anggota]')].filter((el) => this.cocok(el));
         },
         bukaPanel() {
             this.buka = true;
             this.cari = '';
             this.sorot = this.id || (this.terlihat()[0]?.dataset.opsiAnggota ?? '');
             this.$nextTick(() => { this.$refs.cari.focus(); this.gulirKeSorot(); });
         },
         tutup(fokus = true) {
             this.buka = false;
             if (fokus) this.$refs.pemicu.focus();
         },
         geser(arah) {
             const daftar = this.terlihat();
             if (! daftar.length) return;
             const i = daftar.findIndex((el) => el.dataset.opsiAnggota === this.sorot);
             const j = i === -1 ? 0 : Math.min(Math.max(i + arah, 0), daftar.length - 1);
             this.sorot = daftar[j].dataset.opsiAnggota;
             this.$nextTick(() => this.gulirKeSorot());
         },
         gulirKeSorot() {
             this.$refs.daftar.querySelector('[data-opsi-anggota=&quot;' + this.sorot + '&quot;]')?.scrollIntoView({ block: 'nearest' });
         },
         pilihSorot() {
             const el = this.terlihat().find((el) => el.dataset.opsiAnggota === this.sorot) ?? this.terlihat()[0];
             if (el) this.pilih(el);
         },
         pilih(el) {
             const berubah = this.id !== el.dataset.opsiAnggota;
             this.id = el.dataset.opsiAnggota;
             this.label = el.dataset.label;
             this.$refs.nilai.value = this.id;
             this.tutup();
             this.$dispatch('anggota-dipilih', { id: this.id, berubah });
         },
     }"
     x-on:keydown.escape="if (buka) { $event.stopPropagation(); tutup() }"
     x-on:click.outside="if (buka) tutup(false)">
    <input type="hidden" name="anggota_id" value="{{ $terpilih?->id }}" x-ref="nilai">

    @unless ($semua)
        <x-ui.label for="pilih-anggota" value="Anggota" />
    @endunless
    {{-- Pemicu bergaya x-ui.input (h-11, rounded-xl, tepi stone-300), sama dengan kolom cari di sebelahnya di Riwayat. --}}
    <button type="button" id="pilih-anggota" x-ref="pemicu"
            x-on:click="buka ? tutup() : bukaPanel()"
            x-on:keydown.down.prevent="bukaPanel()"
            @if ($semua) aria-label="Anggota" @endif
            aria-haspopup="listbox" aria-controls="daftar-anggota" aria-expanded="false" x-bind:aria-expanded="buka.toString()"
            @class([
                'flex h-11 w-full items-center justify-between gap-3 rounded-xl border bg-white px-3.5 text-left text-sm focus:border-arang focus:outline-none focus:ring-2 focus:ring-arang/20',
                'mt-1.5' => ! $semua,
                'border-red-300' => $salah,
                'border-stone-300' => ! $salah,
            ])
            data-pemicu-anggota>
        {{-- Warna hanya lewat x-bind:class (Alpine tidak bisa melepas kelas statis); sebelum Alpine siap teks mewarisi warna tombol. --}}
        <span class="min-w-0 truncate" x-bind:class="id || semua ? 'text-stone-900' : 'text-stone-400'" x-text="label" data-anggota-terpilih>{{ $labelTerpilih }}</span>
        <x-ui.icon name="chevron-down" class="h-4 w-4 shrink-0 text-stone-500" />
    </button>
    @unless ($semua)
        <x-ui.field-error name="anggota_id" />
    @endunless

    <div x-cloak x-show="buka" x-transition.opacity.duration.100ms
         @class([
             'absolute z-20 mt-2 overflow-hidden rounded-xl border border-stone-200 bg-white shadow-lg',
             'inset-x-0' => ! $semua,
             'inset-x-0 md:left-auto md:w-[26rem]' => $semua,
         ])>
        <div class="relative border-b border-stone-200 p-2">
            <x-ui.icon name="magnifying-glass" class="pointer-events-none absolute left-5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
            <input type="text" x-ref="cari" x-model="cari" autocomplete="off"
                   placeholder="Cari nama atau no. anggota" aria-label="Cari nama atau no. anggota" aria-controls="daftar-anggota"
                   x-on:input="$nextTick(() => { sorot = terlihat()[0]?.dataset.opsiAnggota ?? '' })"
                   x-on:keydown.down.prevent="geser(1)"
                   x-on:keydown.up.prevent="geser(-1)"
                   x-on:keydown.enter.prevent="pilihSorot()"
                   class="block h-10 w-full rounded-lg border border-stone-300 bg-white pl-9 pr-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-arang focus:ring-2 focus:ring-arang/20">
        </div>

        @php
            $opsi = $daftar->map(fn ($anggota) => [
                'id' => (string) $anggota->id,
                'label' => $labelOpsi($anggota),
                'nama' => $anggota->nama,
                'no' => $anggota->no_anggota,
                'saldo' => $saldo !== null ? Format::rupiah($saldo[$anggota->id] ?? 0) : null,
            ]);
            if ($opsiSemua) {
                $opsi->prepend(['id' => '', 'label' => 'Semua anggota', 'nama' => '', 'no' => '', 'saldo' => null]);
            }
        @endphp
        <ul id="daftar-anggota" role="listbox" aria-label="Daftar anggota" x-ref="daftar" class="max-h-72 overflow-y-auto py-1">
            @foreach ($opsi as $item)
                <li role="option" data-opsi-anggota="{{ $item['id'] }}" data-label="{{ $item['label'] }}"
                    data-nama="{{ $item['nama'] }}" data-no="{{ $item['no'] }}"
                    data-kunci="{{ mb_strtolower($item['label']) }}"
                    x-show="cocok($el)"
                    x-on:click="pilih($el)"
                    x-on:mouseenter="sorot = $el.dataset.opsiAnggota"
                    x-bind:aria-selected="(id === $el.dataset.opsiAnggota).toString()"
                    x-bind:class="sorot === $el.dataset.opsiAnggota ? 'bg-stone-100' : ''"
                    class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm text-stone-900">
                    <span class="min-w-0 truncate" data-label-opsi>{{ $item['label'] }}</span>
                    @if ($item['saldo'] !== null)
                        <span class="shrink-0 text-right text-stone-700 tabular-nums" data-saldo-opsi>{{ $item['saldo'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p x-show="terlihat().length === 0" @if ($opsi->isNotEmpty()) x-cloak @endif class="px-3 py-3 text-sm text-stone-500" data-anggota-kosong>Tidak ada anggota yang cocok.</p>
    </div>
</div>

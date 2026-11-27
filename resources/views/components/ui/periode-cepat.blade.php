{{--
    Periode laporan dengan tombol cepat (R1b C.4–C.6), dipakai laporan per anggota dan laporan keseluruhan.
    - periode: $periodeCepat dari LaporanController::periodeCepat() (label → [mulai, selesai] Y-m-d, dihitung di server).
    - mulai / selesai: nilai awal kolom (dari $filter).
    - minAwal (opsional): batas bawah awal (Y-m-d) untuk atribut min dari server (laporan per anggota, mode Satu anggota).
    - batasMin (opsional): ekspresi Alpine yang dibaca dari komponen induk (mis. tanggal bergabung anggota terpilih).
      Jika terisi, rentang tombol dinaikkan ke batas itu (jawaban 5a); tombol yang seluruh rentangnya sebelum batas nonaktif.
    Klik tombol hanya mengisi kedua kolom dan menandai tombol itu aktif; form tetap dikirim lewat "Tampilkan".
    "Pilih tanggal…" hanya memfokuskan tanggal mulai dan aktif bila isi kolom tidak cocok dengan tombol mana pun.
    Kolom tanggal diberi max = hari ini. Nama field tanggal_mulai / tanggal_selesai tetap.
--}}
@props(['periode', 'mulai' => null, 'selesai' => null, 'minAwal' => null, 'batasMin' => null])
@php
    $hariIni = now()->toDateString();
    $rentangServer = function (array $rentang) use ($minAwal): ?array {
        [$m, $s] = [$rentang[0] ?? '', $rentang[1] ?? ''];
        if ($minAwal !== null && $m !== '' && $m < $minAwal) {
            $m = $minAwal;
        }

        return $m !== '' && $s !== '' && $m > $s ? null : [$m, $s];
    };
    $aktifAwal = 'kustom';
    foreach ($periode as $label => $rentang) {
        if ($rentangServer($rentang) === [(string) $mulai, (string) $selesai]) {
            $aktifAwal = $label;
            break;
        }
    }
    $kelasTombol = 'aria-pressed:border-arang aria-pressed:text-stone-900';
@endphp
<div {{ $attributes->merge(['class' => 'space-y-3']) }} data-periode-cepat
     x-data="{
         periode: @js($periode),
         mulai: @js((string) $mulai),
         selesai: @js((string) $selesai),
         batas() { return {{ $batasMin ?? "''" }} || ''; },
         rentang(label) {
             let [m, s] = this.periode[label];
             m = m ?? ''; s = s ?? '';
             const b = this.batas();
             if (b && m && m < b) m = b;
             return m && s && m > s ? null : [m, s];
         },
         aktif() {
             for (const label of Object.keys(this.periode)) {
                 const r = this.rentang(label);
                 if (r && r[0] === this.mulai && r[1] === this.selesai) return label;
             }
             return 'kustom';
         },
         pilih(label) {
             const r = this.rentang(label);
             if (r) [this.mulai, this.selesai] = r;
         },
     }">
    <div>
        <span class="text-sm font-medium text-stone-700">Periode</span>
        <div class="mt-1.5 flex flex-wrap gap-2">
            @foreach ($periode as $label => $rentang)
                <x-ui.button variant="secondary" size="sm" type="button" :class="$kelasTombol" data-periode="{{ $label }}"
                             data-mulai="{{ $rentang[0] }}" data-selesai="{{ $rentang[1] }}"
                             aria-pressed="{{ $aktifAwal === $label ? 'true' : 'false' }}"
                             x-bind:aria-pressed="(aktif() === '{{ $label }}').toString()"
                             x-bind:disabled="rentang('{{ $label }}') === null"
                             x-on:click="pilih('{{ $label }}')">{{ $label }}</x-ui.button>
            @endforeach
            <x-ui.button variant="secondary" size="sm" type="button" :class="$kelasTombol" data-periode="kustom"
                         aria-pressed="{{ $aktifAwal === 'kustom' ? 'true' : 'false' }}"
                         x-bind:aria-pressed="(aktif() === 'kustom').toString()"
                         x-on:click="$refs.mulai.focus()">Pilih tanggal…</x-ui.button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-ui.label for="tanggal_mulai" value="Tanggal mulai" />
            <x-ui.input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ $mulai }}" class="mt-1.5"
                        max="{{ $hariIni }}" :min="$minAwal" x-ref="mulai" x-model="mulai" x-bind:min="batas() || null" />
            <x-ui.field-error name="tanggal_mulai" />
        </div>

        <div>
            <x-ui.label for="tanggal_selesai" value="Tanggal selesai" />
            <x-ui.input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ $selesai }}" class="mt-1.5"
                        max="{{ $hariIni }}" :min="$minAwal" x-model="selesai" x-bind:min="batas() || null" />
            <x-ui.field-error name="tanggal_selesai" />
        </div>
    </div>
</div>

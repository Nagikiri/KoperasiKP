{{--
    Kotak nominal dan isi cepat (F4b A.4, C.5–C.6).
    Input jumlah sengaja HTML biasa (F1 §0.6): urutan atribut id, type, inputmode, autocomplete, name, value tetap;
    partial format-jumlah tidak diubah. Tombol isi cepat (data-isi-cepat) diatur resources/js/app.js: klik MENGGANTI isi
    input, memicu event input (format ribuan ikut jalan), lalu fokus ke input.
    $labelNominal: "Nominal setoran" / "Nominal penarikan".
    $tarik (opsional): tampilkan saldo tersedia (data-saldo-tersedia), peringatan saldo, dan tombol "Semua saldo";
    nilai awal dari $saldoTerpilih (null = belum ada anggota terpilih). Perubahan berikutnya diatur transaksi-tarik.js.
--}}
@php
    use App\Support\Format;

    $tarik ??= false;
    $saldoTerpilih ??= null;
    $salahJumlah = $errors->has('jumlah');
    $isiCepat = [50000, 100000, 200000, 300000, 500000, 1000000];
    $kelasTombol = 'inline-flex h-10 items-center justify-center rounded-full border border-stone-300 bg-white px-3 text-sm font-medium text-stone-800 tabular-nums transition-colors hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-white';
@endphp

<div class="rounded-xl border border-stone-200 p-4" data-kotak-nominal>
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <label for="jumlah" class="text-xs font-medium text-stone-500">{{ $labelNominal }}</label>
        @if ($tarik)
            <p class="text-xs text-stone-500" data-saldo-tersedia>
                <span data-saldo-kosong @if ($saldoTerpilih !== null) hidden @endif>Pilih anggota untuk melihat saldo</span>
                <span data-saldo-ada @if ($saldoTerpilih === null) hidden @endif>Saldo sukarela tersedia <span class="ml-1 text-sm font-semibold text-stone-900 tabular-nums" data-nilai-saldo>{{ $saldoTerpilih !== null ? Format::rupiah($saldoTerpilih) : '' }}</span></span>
            </p>
        @endif
    </div>
    <div class="relative mt-2">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5 text-xl font-medium text-stone-400" aria-hidden="true" data-awalan-rp>Rp</span>
        <input id="jumlah" type="text" inputmode="numeric" autocomplete="off" name="jumlah" value="{{ old('jumlah') }}" class="block h-16 w-full rounded-xl border bg-white pl-14 pr-4 text-3xl font-semibold tracking-tight text-stone-900 tabular-nums placeholder:text-stone-300 focus:border-arang focus:ring-2 focus:ring-arang/20 {{ $salahJumlah ? 'border-red-300' : 'border-stone-300' }}" placeholder="0">
    </div>
    @if ($tarik)
        <p class="mt-1.5 text-xs text-amber-700" data-peringatan-saldo hidden>Melebihi saldo tersedia.</p>
    @endif
    <x-ui.field-error name="jumlah" />
</div>

<div class="mt-3">
    <p class="text-xs font-medium text-stone-500">Isi cepat</p>
    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" data-daftar-isi-cepat>
        @foreach ($isiCepat as $nilai)
            <button type="button" class="{{ $kelasTombol }}" data-isi-cepat="{{ $nilai }}" aria-pressed="false"
                    @disabled($tarik && $saldoTerpilih !== null && $nilai > $saldoTerpilih)>{{ Format::rupiah($nilai) }}</button>
        @endforeach
        @if ($tarik)
            <button type="button" class="{{ $kelasTombol }}" data-isi-cepat="semua" data-nilai="{{ $saldoTerpilih !== null ? (int) floor($saldoTerpilih) : '' }}" aria-pressed="false"
                    @disabled($saldoTerpilih === null || $saldoTerpilih <= 0)>Semua saldo</button>
        @endif
    </div>
</div>

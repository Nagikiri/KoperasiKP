{{--
    Bagian bawah form setor/tarik (F4 §1–§2, H.2.1): jumlah besar dengan awalan "Rp", lalu tanggal dan catatan dua kolom.
    Input jumlah sengaja HTML biasa (F1 §0.6): urutan atribut id, type, inputmode, autocomplete, name, value tetap;
    kelas disalin dari gaya x-ui.input (putih, fokus arang, border-red-300 saat error).
    $peringatanSaldo (opsional, form tarik): tampilkan elemen peringatan "Melebihi saldo tersedia." yang diatur JS.
--}}
@php
    $salahJumlah = $errors->has('jumlah');
@endphp

<div>
    <x-ui.label for="jumlah" value="Jumlah" />
    <div class="relative mt-1.5">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-5 text-xl font-medium text-stone-400" aria-hidden="true" data-awalan-rp>Rp</span>
        <input id="jumlah" type="text" inputmode="numeric" autocomplete="off" name="jumlah" value="{{ old('jumlah') }}" class="block h-16 w-full rounded-xl border bg-white pl-14 pr-4 text-3xl font-semibold tracking-tight text-stone-900 tabular-nums placeholder:text-stone-300 focus:border-arang focus:ring-2 focus:ring-arang/20 {{ $salahJumlah ? 'border-red-300' : 'border-stone-300' }}" placeholder="0">
    </div>
    @if (! empty($peringatanSaldo))
        <p class="mt-1.5 text-xs text-amber-700" data-peringatan-saldo hidden>Melebihi saldo tersedia.</p>
    @endif
    <x-ui.field-error name="jumlah" />
</div>

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <x-ui.label for="tanggal_transaksi" value="Tanggal transaksi" />
        <x-ui.input id="tanggal_transaksi" type="date" name="tanggal_transaksi" value="{{ old('tanggal_transaksi', $tanggalHariIni) }}" max="{{ $tanggalHariIni }}" class="mt-1.5" />
        <x-ui.field-error name="tanggal_transaksi" />
    </div>
    <div>
        <x-ui.label for="keterangan" value="Catatan (opsional)" />
        <x-ui.input id="keterangan" type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="255" autocomplete="off" class="mt-1.5" />
        <x-ui.field-error name="keterangan" />
    </div>
</div>

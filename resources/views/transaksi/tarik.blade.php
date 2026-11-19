@php
    use App\Support\Format;

    // H.2.4 / H.6.2: peta saldo disaring di view, hanya anggota AKTIF (createTarik() tidak diubah).
    $saldoAktif = collect($saldoSukarelaPerAnggota)->only($anggotaAktif->pluck('id'))->all();

    // Saldo tersedia langsung tampil jika ada anggota terpilih (old() atau ?anggota_id=) yang aktif.
    $idTerpilih = (string) old('anggota_id', $anggotaTerpilih);
    $adaTerpilih = $idTerpilih !== '' && $anggotaAktif->contains(fn ($anggota) => (string) $anggota->id === $idTerpilih);
    $saldoTerpilih = $adaTerpilih ? (float) ($saldoAktif[(int) $idTerpilih] ?? 0) : null;
@endphp

{{--
    Form tarik (F4b C): susunan sama dengan setor; jenis tetap Simpanan Sukarela (BR-06, tanpa field jenis).
    Saldo tersedia, isi cepat, dan modal konfirmasi diatur resources/js/transaksi-tarik.js. JS hanya tampilan:
    validasi saldo (BR-08) tetap di server.
--}}
<x-app-layout>
    <x-ui.page-header title="Catat penarikan" subtitle="Penarikan hanya untuk simpanan sukarela." />

    <x-ui.card>
        <form method="POST" action="{{ route('transaksi.tarik.store') }}" data-form-transaksi="tarik">
            @csrf
            <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
            <x-ui.field-error name="kode_referensi" class="mb-4" />

            <div role="note" class="mb-5 flex items-center gap-2.5 rounded-xl bg-sky-50 px-4 py-2.5 text-sm text-sky-700" data-info-tarik>
                <x-ui.icon name="information-circle" class="h-5 w-5 shrink-0" />
                <p>Penarikan hanya bisa dari saldo sukarela.</p>
            </div>

            <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                @include('transaksi.partials.pilih-anggota', ['saldo' => $saldoAktif])

                <div>
                    <x-ui.label for="tanggal_transaksi" value="Tanggal transaksi" />
                    <x-ui.input id="tanggal_transaksi" type="date" name="tanggal_transaksi" value="{{ old('tanggal_transaksi', $tanggalHariIni) }}" max="{{ $tanggalHariIni }}" class="mt-1.5" />
                    <x-ui.field-error name="tanggal_transaksi" />
                </div>

                {{-- C.4: satu kartu Sukarela, sudah terpilih dan tidak bisa diganti; hanya nama jenis, rapat. --}}
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium text-stone-700">Jenis simpanan</p>
                    <div class="mt-1.5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-arang bg-stone-50 px-4 py-2.5 ring-1 ring-arang" data-kartu-jenis-tarik>
                            <span class="block text-sm font-semibold text-stone-900" data-jenis-tarik>{{ $jenisSukarela->nama_jenis }}</span>
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    @include('transaksi.partials.nominal', ['labelNominal' => 'Nominal penarikan', 'tarik' => true, 'saldoTerpilih' => $saldoTerpilih])
                </div>

                <div class="sm:col-span-2">
                    <x-ui.label for="keterangan" value="Catatan (opsional)" />
                    <x-ui.textarea id="keterangan" name="keterangan" rows="2" maxlength="255" placeholder="Tulis catatan bila perlu" class="mt-1.5">{{ old('keterangan') }}</x-ui.textarea>
                    <x-ui.field-error name="keterangan" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-stone-200 pt-5">
                <x-ui.button variant="secondary" :href="route('transaksi.riwayat')">Batal</x-ui.button>
                <x-ui.button>Simpan penarikan</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{--
        C.7: konfirmasi sebelum disimpan. Modal di luar form; tombol "Konfirmasi penarikan" (type=button) memanggil
        requestSubmit() lewat transaksi-tarik.js sehingga kiriman akhir tetap melewati cegah-kirim-ganda.
    --}}
    <x-modal name="konfirmasi-tarik" data-konfirmasi-tarik focusable>
        <div class="p-6">
            <h2 class="text-base font-semibold text-stone-900">Periksa penarikan</h2>
            <p class="mt-1 text-sm text-stone-600">Pastikan data sudah benar sebelum disimpan.</p>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-baseline justify-between gap-4" data-konfirmasi-baris="anggota">
                    <dt class="text-stone-500">Anggota</dt>
                    <dd class="min-w-0 text-right text-stone-900" data-konfirmasi-nilai></dd>
                </div>
                <div class="flex items-baseline justify-between gap-4" data-konfirmasi-baris="jenis">
                    <dt class="text-stone-500">Jenis simpanan</dt>
                    <dd class="text-right text-stone-900" data-konfirmasi-nilai>{{ $jenisSukarela->nama_jenis }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-4" data-konfirmasi-baris="jumlah">
                    <dt class="text-stone-500">Jumlah penarikan</dt>
                    <dd class="text-right text-stone-900 tabular-nums" data-konfirmasi-nilai></dd>
                </div>
                <div class="flex items-baseline justify-between gap-4 border-t border-stone-200 pt-3" data-konfirmasi-baris="sisa">
                    <dt class="font-semibold text-stone-900">Sisa saldo</dt>
                    <dd class="text-right font-semibold text-stone-900 tabular-nums" data-konfirmasi-nilai></dd>
                </div>
            </dl>

            <div class="mt-6 flex justify-end gap-2">
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close')">Batal</x-ui.button>
                <x-ui.button type="button" data-konfirmasi-kirim>Konfirmasi penarikan</x-ui.button>
            </div>
        </div>
    </x-modal>

    <script type="application/json" id="data-saldo">@json($saldoAktif)</script>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

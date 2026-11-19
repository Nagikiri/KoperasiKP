@php
    use App\Support\Format;

    // H.2.4 / H.6.2: peta saldo disaring di view, hanya anggota AKTIF (createTarik() tidak diubah).
    $saldoAktif = collect($saldoSukarelaPerAnggota)->only($anggotaAktif->pluck('id'))->all();

    // Kotak saldo langsung tampil jika ada anggota terpilih (old() atau ?anggota_id=) yang aktif.
    $idTerpilih = (string) old('anggota_id', $anggotaTerpilih);
    $adaTerpilih = $idTerpilih !== '' && $anggotaAktif->contains(fn ($anggota) => (string) $anggota->id === $idTerpilih);
@endphp

{{--
    Form tarik (F4 §2, H.2): sama dengan setor; jenis tetap Simpanan Sukarela (BR-06), kotak saldo tersedia diatur
    resources/js/transaksi-tarik.js. JS hanya tampilan: validasi saldo tetap di server.
--}}
<x-app-layout>
    <div class="mx-auto max-w-xl">
        <x-ui.page-header title="Catat penarikan" subtitle="Penarikan hanya untuk simpanan sukarela." />

        <x-ui.card>
            <form method="POST" action="{{ route('transaksi.tarik.store') }}" class="space-y-5" data-form-transaksi="tarik">
                @csrf
                <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
                <x-ui.field-error name="kode_referensi" />

                <div role="note" class="flex items-start gap-3 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-700" data-info-tarik>
                    <x-ui.icon name="information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>Penarikan hanya untuk Simpanan Sukarela. Simpanan Pokok dan Wajib dikembalikan saat anggota keluar dari koperasi.</p>
                </div>

                <div>
                    <x-ui.label for="anggota_id" value="Anggota" />
                    <x-ui.select id="anggota_id" name="anggota_id" class="mt-1.5">
                        <option value="">Pilih anggota</option>
                        @foreach ($anggotaAktif as $anggota)
                            <option value="{{ $anggota->id }}" @selected($idTerpilih === (string) $anggota->id)>{{ $anggota->no_anggota }} - {{ $anggota->nama }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error name="anggota_id" />

                    {{-- Pembungkus tanpa kelas display supaya atribut hidden tidak dikalahkan utilitas flex. --}}
                    <div data-kotak-saldo @if (! $adaTerpilih) hidden @endif>
                        <div class="mt-2 flex items-center justify-between gap-3 rounded-xl border border-stone-200 bg-stone-50 px-4 py-2.5 text-sm">
                            <span class="text-stone-500">Saldo sukarela tersedia</span>
                            <span class="font-semibold text-stone-900 tabular-nums" data-nilai-saldo>{{ $adaTerpilih ? Format::rupiah($saldoAktif[(int) $idTerpilih] ?? 0) : '' }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-medium text-stone-700">Jenis simpanan</p>
                    <div class="mt-1.5">
                        <x-ui.badge tone="neutral" data-jenis-tarik>{{ $jenisSukarela->nama_jenis }}</x-ui.badge>
                    </div>
                </div>

                @include('transaksi.partials.jumlah-tanggal-catatan', ['peringatanSaldo' => true])

                <div class="space-y-3 border-t border-stone-200 pt-5">
                    <x-ui.button class="w-full">Simpan penarikan</x-ui.button>
                    <a href="{{ route('transaksi.riwayat') }}" class="block text-center text-sm font-medium text-stone-600 hover:text-stone-900">Batal</a>
                </div>
            </form>
        </x-ui.card>
    </div>

    <script type="application/json" id="data-saldo">@json($saldoAktif)</script>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

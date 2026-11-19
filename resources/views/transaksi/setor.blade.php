{{--
    Form setor (F4b B): selebar wadah layout, satu kartu, grid 2 kolom (gap-x-4 gap-y-5).
    Baris 1 Anggota | Tanggal; baris 2 jenis simpanan (kartu radio, keterangan dari database); baris 3 nominal + isi cepat;
    baris 4 catatan; kaki tombol utama.
--}}
<x-app-layout>
    <x-ui.page-header title="Catat setoran" subtitle="Setoran simpanan pokok, wajib, atau sukarela." />

    <x-ui.card>
        <form method="POST" action="{{ route('transaksi.setor.store') }}" data-form-transaksi="setor">
            @csrf
            <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
            <x-ui.field-error name="kode_referensi" class="mb-4" />

            <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                @include('transaksi.partials.pilih-anggota')

                <div>
                    <x-ui.label for="tanggal_transaksi" value="Tanggal transaksi" />
                    <x-ui.input id="tanggal_transaksi" type="date" name="tanggal_transaksi" value="{{ old('tanggal_transaksi', $tanggalHariIni) }}" max="{{ $tanggalHariIni }}" class="mt-1.5" />
                    <x-ui.field-error name="tanggal_transaksi" />
                </div>

                {{-- A.3: tiga kartu sama tinggi; terpilih arang (tanpa merah). Keterangan dari jenis_simpanan.keterangan. --}}
                <fieldset class="sm:col-span-2" data-pilihan-jenis>
                    <legend class="text-sm font-medium text-stone-700">Jenis simpanan</legend>
                    <div class="mt-1.5 grid gap-3 sm:grid-cols-3">
                        @foreach ($jenisSimpanan as $jenis)
                            <label class="block cursor-pointer">
                                <input type="radio" name="jenis_simpanan_id" value="{{ $jenis->id }}" class="peer sr-only" @checked((string) old('jenis_simpanan_id') === (string) $jenis->id)>
                                <span class="block h-full rounded-xl border border-stone-200 bg-white p-4 transition-colors hover:border-stone-300 peer-checked:border-arang peer-checked:bg-stone-50 peer-checked:ring-1 peer-checked:ring-arang peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30" data-kartu-jenis>
                                    <span class="block text-sm font-semibold text-stone-900">{{ $jenis->nama_jenis }}</span>
                                    @if (filled($jenis->keterangan))
                                        <span class="mt-0.5 block text-xs text-stone-500" data-keterangan-jenis>{{ $jenis->keterangan }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.field-error name="jenis_simpanan_id" />
                </fieldset>

                <div class="sm:col-span-2">
                    @include('transaksi.partials.nominal', ['labelNominal' => 'Nominal setoran'])
                </div>

                <div class="sm:col-span-2">
                    <x-ui.label for="keterangan" value="Catatan (opsional)" />
                    <x-ui.textarea id="keterangan" name="keterangan" rows="2" maxlength="255" placeholder="Tulis catatan bila perlu" class="mt-1.5">{{ old('keterangan') }}</x-ui.textarea>
                    <x-ui.field-error name="keterangan" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-stone-200 pt-5">
                <x-ui.button variant="secondary" :href="route('transaksi.riwayat')">Batal</x-ui.button>
                <x-ui.button>Simpan setoran</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

{{--
    Form setor (F4 §1, H.1.4, H.2): satu kartu di tengah; jenis simpanan berupa kartu radio (terpilih arang, bukan merah).
--}}
<x-app-layout>
    <div class="mx-auto max-w-xl">
        <x-ui.page-header title="Catat setoran" subtitle="Setoran simpanan pokok, wajib, atau sukarela." />

        <x-ui.card>
            <form method="POST" action="{{ route('transaksi.setor.store') }}" class="space-y-5" data-form-transaksi="setor">
                @csrf
                <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
                <x-ui.field-error name="kode_referensi" />

                <div>
                    <x-ui.label for="anggota_id" value="Anggota" />
                    <x-ui.select id="anggota_id" name="anggota_id" class="mt-1.5">
                        <option value="">Pilih anggota</option>
                        @foreach ($anggotaAktif as $anggota)
                            <option value="{{ $anggota->id }}" @selected((string) old('anggota_id', $anggotaTerpilih) === (string) $anggota->id)>{{ $anggota->no_anggota }} - {{ $anggota->nama }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.field-error name="anggota_id" />
                </div>

                <fieldset data-pilihan-jenis>
                    <legend class="text-sm font-medium text-stone-700">Jenis simpanan</legend>
                    <div class="mt-1.5 grid gap-3 sm:grid-cols-3">
                        @foreach ($jenisSimpanan as $jenis)
                            <label class="block cursor-pointer">
                                <input type="radio" name="jenis_simpanan_id" value="{{ $jenis->id }}" class="peer sr-only" @checked((string) old('jenis_simpanan_id') === (string) $jenis->id)>
                                <span class="block h-full rounded-xl border border-stone-200 bg-white px-4 py-3 transition-colors hover:border-stone-300 peer-checked:border-arang peer-checked:bg-stone-50 peer-checked:ring-1 peer-checked:ring-arang peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30" data-kartu-jenis>
                                    <span class="block text-sm font-medium text-stone-900">{{ $jenis->nama_jenis }}</span>
                                    @if (filled($jenis->keterangan))
                                        <span class="mt-1 block text-xs leading-snug text-stone-500" data-keterangan-jenis>{{ $jenis->keterangan }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-ui.field-error name="jenis_simpanan_id" />
                </fieldset>

                @include('transaksi.partials.jumlah-tanggal-catatan')

                <div class="space-y-3 border-t border-stone-200 pt-5">
                    <x-ui.button class="w-full">Simpan setoran</x-ui.button>
                    <a href="{{ route('transaksi.riwayat') }}" class="block text-center text-sm font-medium text-stone-600 hover:text-stone-900">Batal</a>
                </div>
            </form>
        </x-ui.card>
    </div>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

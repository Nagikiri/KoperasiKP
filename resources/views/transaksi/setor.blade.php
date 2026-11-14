<x-app-layout>
    <h1>Setor Simpanan</h1>

    <form method="POST" action="{{ route('transaksi.setor.store') }}">
        @csrf
        <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
        @error('kode_referensi')
            <p>{{ $message }}</p>
        @enderror

        <div>
            <label for="anggota_id">Anggota</label>
            <select id="anggota_id" name="anggota_id">
                <option value="">-- Pilih anggota --</option>
                @foreach ($anggotaAktif as $anggota)
                    <option value="{{ $anggota->id }}" @selected((string) old('anggota_id', $anggotaTerpilih) === (string) $anggota->id)>
                        {{ $anggota->no_anggota }} - {{ $anggota->nama }}
                    </option>
                @endforeach
            </select>
            @error('anggota_id')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="jenis_simpanan_id">Jenis Simpanan</label>
            <select id="jenis_simpanan_id" name="jenis_simpanan_id">
                <option value="">-- Pilih jenis --</option>
                @foreach ($jenisSimpanan as $jenis)
                    <option value="{{ $jenis->id }}" @selected((string) old('jenis_simpanan_id') === (string) $jenis->id)>
                        {{ $jenis->nama_jenis }}
                    </option>
                @endforeach
            </select>
            @error('jenis_simpanan_id')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="jumlah">Jumlah (Rp)</label>
            <input id="jumlah" type="text" inputmode="numeric" autocomplete="off" name="jumlah" value="{{ old('jumlah') }}">
            @error('jumlah')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="tanggal_transaksi">Tanggal Transaksi</label>
            <input id="tanggal_transaksi" type="date" name="tanggal_transaksi"
                   value="{{ old('tanggal_transaksi', $tanggalHariIni) }}" max="{{ $tanggalHariIni }}">
            @error('tanggal_transaksi')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="keterangan">Catatan (opsional)</label>
            <input id="keterangan" type="text" name="keterangan" value="{{ old('keterangan') }}" maxlength="255">
            @error('keterangan')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Simpan Setoran</button>
        <a href="{{ route('transaksi.riwayat') }}">Batal</a>
    </form>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

@php
    use App\Support\Format;
@endphp

<x-app-layout>
    <h1>Tarik Simpanan</h1>

    <p>Penarikan hanya untuk Simpanan Sukarela. Simpanan Pokok dan Wajib dikembalikan saat anggota keluar dari koperasi.</p>

    {{-- onsubmit: tombol dinonaktifkan setelah klik pertama. Pengaman utama tetap kode_referensi di server (BR-19). --}}
    <form method="POST" action="{{ route('transaksi.tarik.store') }}"
          onsubmit="this.querySelector('button[type=submit]').disabled = true;">
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
                        (Saldo Sukarela: {{ Format::rupiah($saldoSukarelaPerAnggota[$anggota->id] ?? 0) }})
                    </option>
                @endforeach
            </select>
            @error('anggota_id')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <p>Jenis Simpanan: {{ $jenisSukarela->nama_jenis }}</p>
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

        <button type="submit">Simpan Penarikan</button>
        <a href="{{ route('transaksi.riwayat') }}">Batal</a>
    </form>

    @include('transaksi.partials.format-jumlah')
</x-app-layout>

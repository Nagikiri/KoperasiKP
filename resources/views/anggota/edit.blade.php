<x-app-layout>
    <h1>Ubah Data Anggota</h1>

    <p>No. Anggota: {{ $anggota->no_anggota }}</p>

    <form method="POST" action="{{ route('anggota.update', $anggota) }}">
        @csrf
        @method('PUT')

        <div>
            <label for="nama">Nama</label>
            <input id="nama" type="text" name="nama" value="{{ old('nama', $anggota->nama) }}">
            @error('nama')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nik">NIK</label>
            <input id="nik" type="text" name="nik" value="{{ old('nik', $anggota->nik) }}" maxlength="16">
            @error('nik')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat">{{ old('alamat', $anggota->alamat) }}</textarea>
            @error('alamat')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="no_telepon">No. Telepon</label>
            <input id="no_telepon" type="text" name="no_telepon" value="{{ old('no_telepon', $anggota->no_telepon) }}">
            @error('no_telepon')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="tanggal_bergabung">Tanggal Bergabung</label>
            <input id="tanggal_bergabung" type="date" name="tanggal_bergabung"
                   value="{{ old('tanggal_bergabung', $anggota->tanggal_bergabung->format('Y-m-d')) }}">
            @error('tanggal_bergabung')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Simpan Perubahan</button>
        <a href="{{ route('anggota.show', $anggota) }}">Batal</a>
    </form>
</x-app-layout>

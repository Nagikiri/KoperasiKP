<x-app-layout>
    <h1>Tambah Anggota</h1>

    <p>Nomor anggota dibuat otomatis oleh sistem.</p>

    <form method="POST" action="{{ route('anggota.store') }}">
        @csrf

        <div>
            <label for="nama">Nama</label>
            <input id="nama" type="text" name="nama" value="{{ old('nama') }}">
            @error('nama')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nik">NIK</label>
            <input id="nik" type="text" name="nik" value="{{ old('nik') }}" maxlength="16">
            @error('nik')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat">{{ old('alamat') }}</textarea>
            @error('alamat')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="no_telepon">No. Telepon</label>
            <input id="no_telepon" type="text" name="no_telepon" value="{{ old('no_telepon') }}">
            @error('no_telepon')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="tanggal_bergabung">Tanggal Bergabung</label>
            <input id="tanggal_bergabung" type="date" name="tanggal_bergabung"
                   value="{{ old('tanggal_bergabung', now()->format('Y-m-d')) }}">
            @error('tanggal_bergabung')
                <p>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Simpan</button>
        <a href="{{ route('anggota.index') }}">Batal</a>
    </form>
</x-app-layout>

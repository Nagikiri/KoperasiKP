<x-app-layout>
    <h1>Data Anggota</h1>

    @if (session('success'))
        <p>{{ session('success') }}</p>
    @endif

    @if (session('info'))
        <p>{{ session('info') }}</p>
    @endif

    <form method="GET" action="{{ route('anggota.index') }}">
        <label for="search">Cari nama / no. anggota</label>
        <input id="search" type="text" name="search" value="{{ $search }}">
        <button type="submit">Cari</button>
    </form>

    <p><a href="{{ route('anggota.create') }}">Tambah Anggota</a></p>

    @if ($anggota->isEmpty())
        <p>Tidak ada data anggota.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>No. Anggota</th>
                    <th>Nama</th>
                    <th>NIK</th>
                    <th>No. Telepon</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($anggota as $item)
                    <tr>
                        <td>{{ $item->no_anggota }}</td>
                        <td>{{ $item->nama }}</td>
                        <td>{{ $item->nik }}</td>
                        <td>{{ $item->no_telepon ?? '-' }}</td>
                        <td>{{ ucfirst($item->status) }}</td>
                        <td>
                            <a href="{{ route('anggota.show', $item) }}">Detail</a>
                            <a href="{{ route('anggota.edit', $item) }}">Ubah</a>
                            @if ($item->isAktif())
                                <form method="POST" action="{{ route('anggota.nonaktifkan', $item) }}"
                                      onsubmit="return confirm('Nonaktifkan anggota ini? Riwayat transaksi tetap tersimpan.')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit">Nonaktifkan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{ $anggota->links() }}
</x-app-layout>

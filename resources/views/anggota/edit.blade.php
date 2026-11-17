<x-app-layout>
    <x-ui.page-header title="Ubah data anggota" :back="route('anggota.show', $anggota)" />

    <form method="POST" action="{{ route('anggota.update', $anggota) }}">
        @csrf
        @method('PUT')

        @include('anggota.partials.form-isi', ['anggota' => $anggota])
    </form>
</x-app-layout>

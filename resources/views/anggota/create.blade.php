<x-app-layout>
    <x-ui.page-header title="Tambah anggota" :back="route('anggota.index')" />

    <form method="POST" action="{{ route('anggota.store') }}">
        @csrf
        <input type="hidden" name="kode_referensi" value="{{ $kodeReferensi }}">
        <x-ui.field-error name="kode_referensi" class="mb-4" />

        @include('anggota.partials.form-isi', ['anggota' => null])
    </form>
</x-app-layout>

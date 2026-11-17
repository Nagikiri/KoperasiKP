{{--
    Isi sel kolom Transaksi (F3 H.1.3): kotak ikon setor/tarik, label Setor/Tarik, dan nama jenis simpanan di bawahnya.
    Dipakai di dashboard (transaksi terbaru) dan detail anggota. jenis: setor|tarik; namaJenis: nama jenis simpanan.
--}}
@props(['jenis', 'namaJenis'])
@php $setor = $jenis === \App\Models\TransaksiSimpanan::SETOR; @endphp
<div class="flex items-center gap-3">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $setor ? 'bg-setor-50 text-setor' : 'bg-tarik-50 text-tarik' }}">
        <x-ui.icon :name="$setor ? 'arrow-down-tray' : 'arrow-up-tray'" class="w-5 h-5" />
    </span>
    <div class="min-w-0">
        <div class="font-medium text-stone-900">{{ $setor ? 'Setor' : 'Tarik' }}</div>
        <div class="text-xs text-stone-500">{{ $namaJenis }}</div>
    </div>
</div>

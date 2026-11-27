{{--
    Sel anggota seragam (R1 E.1, acuan tabel Riwayat): nama bertaut ke detail (garis bawah hanya saat hover), di bawahnya
    no. anggota mono kecil; anggota nonaktif diberi "· ● Nonaktif" di baris nomor. Tanpa avatar inisial (R1 C.6).
    anggota: model Anggota (id, nama, no_anggota, status). redup: nama stone-500 untuk baris nonaktif (daftar anggota).
    potong: nama dan nomor dipotong dengan ellipsis bila sempit (daftar anggota, belum setor wajib); Riwayat tanpa potong.
    aktif: opsional; diisi bila query tidak memuat kolom status tetapi daftarnya pasti anggota aktif (belum setor wajib).
    penanda: false = tanpa "· ● Nonaktif" di baris nomor (R1b A: Data Anggota, karena kolom Status sudah ada).
--}}
@props(['anggota', 'redup' => false, 'potong' => false, 'aktif' => null, 'penanda' => true])
@php $aktif ??= $anggota->status === \App\Models\Anggota::STATUS_AKTIF; @endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <a href="{{ route('anggota.show', $anggota) }}" data-tautan-detail
       @class([
           'rounded-sm font-medium underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-arang/30',
           'block truncate' => $potong,
           'text-stone-500' => $redup && ! $aktif,
           'text-stone-900' => ! ($redup && ! $aktif),
       ])>{{ $anggota->nama }}</a>
    <div class="flex items-center gap-1.5 text-xs text-stone-500" data-baris-no-anggota>
        <span @class(['font-mono', 'truncate' => $potong])>{{ $anggota->no_anggota }}</span>
        @if (! $aktif && $penanda)
            <span aria-hidden="true">·</span>
            <span class="inline-flex shrink-0 items-center gap-1 text-stone-600" data-penanda-nonaktif><span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full bg-red-600"></span>Nonaktif</span>
        @endif
    </div>
</div>

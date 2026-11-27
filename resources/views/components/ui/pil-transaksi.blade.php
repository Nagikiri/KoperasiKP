{{--
    Pil transaksi (R1 B.5 / E, acuan tabel Riwayat; menggantikan x-ui.jenis-transaksi): cap kecil bersudut 4 px, bergaris
    stone-300, titik 6 px setor/tarik, label Setor/Tarik, lalu "· {jenis}" tanpa awalan "Simpanan ". Jangkar: data-pil-transaksi.
    jenis: setor|tarik; namaJenis: nama jenis simpanan.
--}}
@props(['jenis', 'namaJenis'])
@php $setor = $jenis === \App\Models\TransaksiSimpanan::SETOR; @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded border border-stone-300 bg-white px-2 py-0.5 text-[13px] leading-5 text-stone-800']) }} data-pil-transaksi="{{ $setor ? 'setor' : 'tarik' }}"><span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $setor ? 'bg-setor' : 'bg-tarik' }}"></span><span class="font-medium">{{ $setor ? 'Setor' : 'Tarik' }}</span><span class="text-stone-500" data-pil-jenis>· {{ str_replace('Simpanan ', '', $namaJenis) }}</span></span>

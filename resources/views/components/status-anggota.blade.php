{{--
    Status anggota (F3b B.4, H.2.6): pil bergaris tepi, titik 6 px berwarna (Aktif emerald-600, Nonaktif red-600).
    Berlaku di semua halaman. Jangkar test: data-status-anggota.
--}}
@props(['status'])
@php($aktif = $status === \App\Models\Anggota::STATUS_AKTIF)
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-stone-300 bg-white px-2 py-0.5 text-[13px] font-medium leading-5 '.($aktif ? 'text-stone-800' : 'text-stone-600')]) }} data-status-anggota="{{ $status }}"><span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full {{ $aktif ? 'bg-emerald-600' : 'bg-red-600' }}"></span>{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>

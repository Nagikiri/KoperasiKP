{{-- Status anggota berwarna (00 §4.7): Aktif hijau, Nonaktif merah. Gaya inline agar tampil juga di PDF. --}}
@props(['status'])
@php($aktif = $status === \App\Models\Anggota::STATUS_AKTIF)
<span data-status-anggota="{{ $status }}" style="color: {{ $aktif ? '#15803d' : '#b91c1c' }}; font-weight: bold;">{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>

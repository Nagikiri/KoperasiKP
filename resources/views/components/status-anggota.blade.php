{{-- Status anggota sebagai badge (F1 §3.12): Aktif hijau, Nonaktif merah. Jangkar test: data-status-anggota. --}}
@props(['status'])
@php($aktif = $status === \App\Models\Anggota::STATUS_AKTIF)
<x-ui.badge :tone="$aktif ? 'success' : 'danger'" data-status-anggota="{{ $status }}">{{ $aktif ? 'Aktif' : 'Nonaktif' }}</x-ui.badge>

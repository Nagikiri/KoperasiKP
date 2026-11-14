{{-- Nama lengkap anggota + penanda "(Nonaktif)" merah bila nonaktif (BR-21). --}}
@props(['nama', 'status'])
{{ $nama }}@if ($status !== \App\Models\Anggota::STATUS_AKTIF) <span data-penanda-nonaktif style="color: #b91c1c; font-weight: bold;">(Nonaktif)</span>@endif

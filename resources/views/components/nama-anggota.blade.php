{{--
    Nama lengkap anggota + penanda "(Nonaktif)" bila nonaktif (BR-21, F1 §3.12).
    Dipakai juga di PDF (dompdf, tanpa Tailwind): warna merah penanda wajib lewat style inline.
--}}
@props(['nama', 'status'])
@if ($status === \App\Models\Anggota::STATUS_AKTIF)<span data-status-anggota="aktif">{{ $nama }}</span>@else{{ $nama }} <span data-status-anggota="nonaktif" class="ml-1 inline-flex items-center rounded bg-red-50 px-2 py-0.5 text-xs" style="color: #b91c1c; font-weight: bold;">(Nonaktif)</span>@endif

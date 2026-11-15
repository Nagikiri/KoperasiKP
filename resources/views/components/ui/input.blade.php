{{--
    Input (F1 §3.4). Atribut (name, value, type, dll.) diteruskan apa adanya; bag untuk penanda error.
    pil: bentuk pil h-12 (halaman login); ikonKiri / ruangKanan: sisakan ruang untuk ikon atau tombol di dalam input.
--}}
@props(['bag' => 'default', 'pil' => false, 'ikonKiri' => false, 'ruangKanan' => false])
@php
    $salah = $attributes->has('name') && $errors->getBag($bag)->has($attributes->get('name'));
    $bentuk = $pil ? 'h-12 rounded-full' : 'h-11 rounded-xl';
    $kiri = $ikonKiri ? 'pl-11' : ($pil ? 'pl-5' : 'pl-3.5');
    $kanan = $ruangKanan ? 'pr-12' : ($pil ? 'pr-5' : 'pr-3.5');
@endphp
<input {{ $attributes->merge(['class' => "block w-full {$bentuk} {$kiri} {$kanan} bg-stone-50 border text-sm text-stone-900 placeholder:text-stone-400 focus:bg-white focus:border-merah focus:ring-2 focus:ring-merah/15 ".($salah ? 'border-red-300' : 'border-stone-200')]) }}>

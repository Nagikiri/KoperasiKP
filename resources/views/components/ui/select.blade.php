{{-- Select (F1 §3.4). Opsi ditulis di slot; bag untuk penanda error. --}}
@props(['bag' => 'default'])
@php
    $salah = $attributes->has('name') && $errors->getBag($bag)->has($attributes->get('name'));
@endphp
<select {{ $attributes->merge(['class' => 'block w-full h-11 rounded-xl bg-stone-50 border px-3.5 pr-10 text-sm text-stone-900 focus:bg-white focus:border-merah focus:ring-2 focus:ring-merah/15 '.($salah ? 'border-red-300' : 'border-stone-200')]) }}>{{ $slot }}</select>

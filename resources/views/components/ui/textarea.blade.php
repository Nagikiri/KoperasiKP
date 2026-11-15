{{-- Textarea (F1 §3.4). Isi awal ditulis di slot; bag untuk penanda error. --}}
@props(['bag' => 'default'])
@php
    $salah = $attributes->has('name') && $errors->getBag($bag)->has($attributes->get('name'));
@endphp
<textarea {{ $attributes->merge(['class' => 'block w-full rounded-xl bg-stone-50 border px-3.5 py-2.5 text-sm text-stone-900 placeholder:text-stone-400 focus:bg-white focus:border-merah focus:ring-2 focus:ring-merah/15 '.($salah ? 'border-red-300' : 'border-stone-200')]) }}>{{ $slot }}</textarea>

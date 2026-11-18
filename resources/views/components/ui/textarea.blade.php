{{-- Textarea (F1 §3.4; gaya disamakan dengan x-ui.input sejak F3b C.2). Isi awal ditulis di slot; bag untuk penanda error. --}}
@props(['bag' => 'default'])
@php
    $salah = $attributes->has('name') && $errors->getBag($bag)->has($attributes->get('name'));
@endphp
<textarea {{ $attributes->merge(['class' => 'block w-full rounded-xl bg-white border px-3.5 py-2.5 text-sm text-stone-900 placeholder:text-stone-400 focus:border-arang focus:ring-2 focus:ring-arang/20 '.($salah ? 'border-red-300' : 'border-stone-300')]) }}>{{ $slot }}</textarea>

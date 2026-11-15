{{-- Label field (F1 §3.4), ditulis di atas input. --}}
@props(['value' => null])
<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-stone-700']) }}>{{ $value ?? $slot }}</label>

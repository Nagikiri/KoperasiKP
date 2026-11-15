{{-- Pesan error per field (F1 §3.4). bag: nama error bag (mis. updatePassword). --}}
@props(['name', 'bag' => 'default'])
@error($name, $bag)
    <p {{ $attributes->merge(['class' => 'text-xs text-red-700 mt-1.5']) }}>{{ $message }}</p>
@enderror

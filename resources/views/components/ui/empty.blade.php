{{-- Keadaan kosong (F1 §3.8). Slot utama = aksi opsional. --}}
@props(['title', 'description' => null, 'icon' => 'information-circle'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-12 text-center']) }}>
    <x-ui.icon :name="$icon" class="w-10 h-10 text-stone-300" />
    <p class="mt-3 text-sm font-medium text-stone-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-xs text-stone-500">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>

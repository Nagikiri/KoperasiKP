{{--
    Modal konfirmasi nonaktifkan anggota (F3 §2, H.2.1): satu per anggota, nama nonaktifkan-{id}.
    Dibuka dengan $dispatch('open-modal', 'nonaktifkan-{id}'). Pembungkus membawa data-modal-nonaktifkan;
    form PATCH ada di dalamnya, tanpa onsubmit (cegah-kirim-ganda menangkap submit-nya).
--}}
<x-modal :name="'nonaktifkan-'.$anggota->id" focusable data-modal-nonaktifkan>
    <form method="POST" action="{{ route('anggota.nonaktifkan', $anggota) }}" class="p-6">
        @csrf
        @method('PATCH')

        <div class="flex items-start gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700">
                <x-ui.icon name="no-symbol" class="w-5 h-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-stone-900">Nonaktifkan anggota?</h2>
                <p class="mt-1.5 text-sm text-stone-600">{{ $anggota->nama }} ({{ $anggota->no_anggota }}) tidak bisa lagi melakukan setor atau tarik. Data dan riwayat tetap tersimpan.</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close')">Batal</x-ui.button>
            <x-ui.button variant="danger" type="submit">Nonaktifkan</x-ui.button>
        </div>
    </form>
</x-modal>

{{--
    Isi form tambah/ubah anggota (F3 §3): tiga kartu bertumpuk + bilah aksi.
    $anggota: null (tambah) atau model (ubah). Nama field dan old() tetap seperti kontrak lama.
--}}
@php
    $ubah = $anggota !== null;
    $kartu = [
        'pribadi' => ['judul' => 'Data pribadi', 'ikon' => 'users'],
        'kontak' => ['judul' => 'Kontak', 'ikon' => 'information-circle'],
        'keanggotaan' => ['judul' => 'Keanggotaan', 'ikon' => 'calendar'],
    ];
@endphp

<div class="max-w-3xl space-y-6">
    @foreach ($kartu as $kunci => $info)
        <x-ui.card data-kartu-form="{{ $kunci }}">
            <x-slot:header>
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-stone-100 text-stone-600">
                        <x-ui.icon :name="$info['ikon']" class="w-5 h-5" />
                    </span>
                    <h2 class="text-base font-semibold text-stone-900">{{ $info['judul'] }}</h2>
                </div>
            </x-slot:header>

            <div class="grid gap-5 sm:grid-cols-2">
                @if ($kunci === 'pribadi')
                    <div class="sm:col-span-2">
                        <x-ui.label for="nama" value="Nama lengkap" />
                        <x-ui.input id="nama" type="text" name="nama" value="{{ old('nama', $anggota?->nama) }}" class="mt-1.5" autocomplete="off" />
                        <x-ui.field-error name="nama" />
                    </div>
                    <div>
                        <x-ui.label for="nik" value="NIK" />
                        <x-ui.input id="nik" type="text" name="nik" value="{{ old('nik', $anggota?->nik) }}" maxlength="16" inputmode="numeric" autocomplete="off" class="mt-1.5 font-mono tabular-nums" />
                        <p class="mt-1.5 text-xs text-stone-500">16 digit sesuai KTP</p>
                        <x-ui.field-error name="nik" />
                    </div>
                @elseif ($kunci === 'kontak')
                    <div>
                        <x-ui.label for="no_telepon" value="No. telepon" />
                        <x-ui.input id="no_telepon" type="text" name="no_telepon" value="{{ old('no_telepon', $anggota?->no_telepon) }}" placeholder="081234567890" inputmode="tel" class="mt-1.5" />
                        <x-ui.field-error name="no_telepon" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-ui.label for="alamat" value="Alamat" />
                        <x-ui.textarea id="alamat" name="alamat" rows="3" class="mt-1.5">{{ old('alamat', $anggota?->alamat) }}</x-ui.textarea>
                        <x-ui.field-error name="alamat" />
                    </div>
                @else
                    <div>
                        <x-ui.label for="tanggal_bergabung" value="Tanggal bergabung" />
                        <x-ui.input id="tanggal_bergabung" type="date" name="tanggal_bergabung"
                                    value="{{ old('tanggal_bergabung', $ubah ? $anggota->tanggal_bergabung->format('Y-m-d') : now()->format('Y-m-d')) }}" class="mt-1.5" />
                        <x-ui.field-error name="tanggal_bergabung" />
                    </div>
                    <div>
                        @if ($ubah)
                            <p class="text-sm font-medium text-stone-700">Nomor anggota</p>
                            <p class="mt-1.5 flex h-11 items-center font-mono text-sm text-stone-900" data-nomor-anggota>{{ $anggota->no_anggota }}</p>
                        @else
                            <div class="flex items-start gap-2 rounded-xl bg-stone-50 px-3.5 py-3 text-sm text-stone-600 sm:mt-6">
                                <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-stone-400" />
                                <p>Nomor anggota dibuat otomatis oleh sistem.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-ui.card>
    @endforeach

    <div class="flex justify-end gap-2">
        <x-ui.button variant="secondary" :href="$ubah ? route('anggota.show', $anggota) : route('anggota.index')">Batal</x-ui.button>
        <x-ui.button>{{ $ubah ? 'Simpan perubahan' : 'Simpan anggota' }}</x-ui.button>
    </div>
</div>

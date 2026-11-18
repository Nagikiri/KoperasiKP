@php
    use App\Models\Anggota;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;
    use Illuminate\Support\Str;

    $aktif = $anggota->isAktif();

    // F3b D.4: pilihan filter riwayat. Kunci kosong = tanpa filter.
    $segmenRiwayat = ['' => 'Semua', TransaksiSimpanan::SETOR => 'Setoran', TransaksiSimpanan::TARIK => 'Penarikan'];
    $pilihanJenis = ['' => 'Semua jenis'] + $jenisSimpanan->mapWithKeys(fn ($jenis) => [(string) $jenis->id => str_replace('Simpanan ', '', $jenis->nama_jenis)])->all();
    $pilihanWaktu = ['' => 'Semua waktu', 'bulan_ini' => 'Bulan ini', 'bulan_lalu' => 'Bulan lalu', '3_bulan' => '3 bulan terakhir', 'tahun_ini' => 'Tahun ini'];
    $labelWaktu = $pilihanWaktu + ['kustom' => 'Pilih tanggal…'];
    $rentangAktif = $filterRiwayat['rentang'] ?? '';
    $adaFilterRiwayat = collect($filterRiwayat)->contains(fn ($nilai) => $nilai !== null);
    $urlReset = route('anggota.show', $anggota).'#riwayat';
    $kelasOpsi = 'group flex w-full items-center justify-between px-3 py-2 text-left text-sm text-stone-700 hover:bg-stone-50 focus:bg-stone-50 focus:outline-none aria-checked:font-medium aria-checked:text-stone-900';
@endphp

<x-app-layout>
    <x-ui.page-header :title="$anggota->nama" :subtitle="$anggota->no_anggota" :back="route('anggota.index')">
        <x-slot:badge>
            <x-status-anggota :status="$anggota->status" />
        </x-slot:badge>
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('anggota.edit', $anggota)" icon="pencil-square">Ubah</x-ui.button>
            @if ($aktif)
                <x-ui.button variant="danger" type="button" icon="no-symbol"
                             x-on:click="$dispatch('open-modal', 'nonaktifkan-{{ $anggota->id }}')">Nonaktifkan</x-ui.button>
                <x-ui.button :href="route('transaksi.setor.create', ['anggota_id' => $anggota->id])" icon="arrow-down-tray">Catat setoran</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- F3 H.1.2 / BR-21: nonaktif dengan saldo > 0 → banner di atas konten, teks tepat satu kali. --}}
    @if (Anggota::perluKeteranganSaldo($anggota->status, $totalSaldo))
        <div data-keterangan-saldo-nonaktif role="note" class="mb-6 flex items-start gap-3 rounded-xl border border-emas/30 bg-emas-50 px-4 py-3 text-sm text-emas-700">
            <x-ui.icon name="exclamation-triangle" class="h-5 w-5 shrink-0" />
            <p>{{ Anggota::KETERANGAN_SALDO_NONAKTIF }}</p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{--
            Revisi pasca-F3b: kartu Saldo kembali ke desain sebelum F3b (total di badan, tiga kotak kecil).
            Kartu meregang setinggi kartu Identitas: badan kartu flex-1 dan kotak mengisi sisa tinggi.
        --}}
        <x-ui.card class="flex flex-col lg:col-span-2 [&>div:last-child]:flex [&>div:last-child]:flex-1 [&>div:last-child]:flex-col" data-kartu-saldo>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Saldo simpanan</h2>
            </x-slot:header>

            <p class="text-sm text-stone-500">Total saldo</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular-nums" data-total-saldo>{{ Format::rupiah($totalSaldo) }}</p>

            <div class="mt-6 grid flex-1 gap-3 sm:grid-cols-3">
                @foreach ($saldoPerJenis as $saldo)
                    <div class="flex flex-col justify-center rounded-xl border border-stone-200 bg-stone-50/60 px-5 py-5" data-saldo-jenis>
                        <p class="text-xs text-stone-500">{{ $saldo['nama_jenis'] }}</p>
                        <p class="mt-1.5 whitespace-nowrap text-lg font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($saldo['saldo']) }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Revisi pasca-F3b: daftar deskripsi label (ikon + teks, lebar 9rem) | nilai, dipisah garis tipis. --}}
        @php
            $barisIdentitas = [
                'nik' => ['label' => 'NIK', 'ikon' => 'identification', 'nilai' => $anggota->nik],
                'no_telepon' => ['label' => 'No. telepon', 'ikon' => 'phone', 'nilai' => $anggota->no_telepon],
                'alamat' => ['label' => 'Alamat', 'ikon' => 'map-pin', 'nilai' => $anggota->alamat],
                'bergabung' => ['label' => 'Bergabung', 'ikon' => 'calendar', 'nilai' => Format::tanggal($anggota->tanggal_bergabung)],
            ];
        @endphp
        <x-ui.card padding="px-6 py-1" data-kartu-identitas>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Identitas</h2>
            </x-slot:header>

            <dl class="divide-y divide-stone-100">
                @foreach ($barisIdentitas as $kunci => $baris)
                    <div class="flex gap-4 py-3" data-baris-identitas="{{ $kunci }}">
                        <dt class="flex w-36 shrink-0 items-center gap-2 self-start pt-0.5 text-xs text-stone-500">
                            <x-ui.icon :name="$baris['ikon']" class="h-4 w-4 shrink-0 text-stone-400" />
                            {{ $baris['label'] }}
                        </dt>
                        <dd class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 text-sm font-medium text-stone-900">
                            @if (filled($baris['nilai']))
                                <span @class(['min-w-0 break-words', 'font-mono tabular-nums' => $kunci === 'nik', 'whitespace-pre-line' => $kunci === 'alamat']) data-nilai-identitas>{{ $baris['nilai'] }}</span>
                                @if ($kunci === 'bergabung')
                                    <span class="text-xs font-normal text-stone-500" data-lama-bergabung>· {{ Format::sejak($anggota->tanggal_bergabung) }}</span>
                                @elseif ($kunci === 'nik')
                                    @include('anggota.partials.tombol-salin', ['teks' => $anggota->nik, 'label' => 'Salin NIK', 'kunci' => 'nik'])
                                @elseif ($kunci === 'no_telepon')
                                    @include('anggota.partials.tombol-salin', ['teks' => $anggota->no_telepon, 'label' => 'Salin nomor telepon', 'kunci' => 'no_telepon'])
                                @endif
                            @else
                                –
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <x-ui.card padding="none" class="mt-6 scroll-mt-6" id="riwayat" data-kartu-riwayat>
        <x-slot:header>
            <h2 class="text-base font-semibold text-stone-900">Riwayat transaksi</h2>
        </x-slot:header>

        @if (! $punyaTransaksi)
            <x-ui.empty icon="banknotes" title="Belum ada transaksi untuk anggota ini." />
        @else
            {{--
                F3b D.4 / H.3.1–H.3.2 / H.5.3: semua pilihan langsung terkirim, kecuali "Pilih tanggal…" yang hanya
                memunculkan dua kolom tanggal; form terkirim setelah keduanya terisi. Form tidak membawa `page`.
            --}}
            <form method="GET" action="{{ route('anggota.show', $anggota) }}#riwayat" data-filter-riwayat
                  x-data="{
                      rentang: @js($rentangAktif),
                      kirim() { this.$nextTick(() => this.$root.requestSubmit()) },
                      cekTanggal() { if (this.$refs.dari.value && this.$refs.sampai.value) this.kirim() },
                  }"
                  class="space-y-3 border-b border-stone-200 px-6 py-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <fieldset class="inline-flex rounded-full border border-stone-200 bg-stone-50 p-0.5">
                        <legend class="sr-only">Jenis transaksi</legend>
                        @foreach ($segmenRiwayat as $nilai => $teks)
                            <label class="cursor-pointer">
                                <input type="radio" name="jenis_transaksi" value="{{ $nilai }}" class="peer sr-only" @checked(($filterRiwayat['jenis_transaksi'] ?? '') === $nilai)
                                       x-on:change="kirim()">
                                <span class="inline-flex h-8 items-center rounded-full px-3 text-xs font-medium text-stone-500 transition-colors hover:text-stone-900 peer-checked:bg-arang peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-arang/30">{{ $teks }}</span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="flex flex-wrap items-center gap-2">
                        {{-- Dropdown jenis simpanan (pola menu dropdown filter dashboard). --}}
                        <div class="relative" data-dropdown-riwayat="jenis_simpanan_id" x-data="{ buka: false }"
                             x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                             x-on:click.outside="buka = false">
                            <input type="hidden" name="jenis_simpanan_id" value="{{ $filterRiwayat['jenis_simpanan_id'] ?? '' }}" x-ref="nilai">
                            <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                         aria-label="Jenis simpanan" aria-haspopup="menu" aria-controls="menu-riwayat-jenis" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                <span data-dropdown-label>{{ $pilihanJenis[$filterRiwayat['jenis_simpanan_id'] ?? ''] ?? 'Semua jenis' }}</span>
                                <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                            </x-ui.button>
                            <div id="menu-riwayat-jenis" role="menu" aria-label="Jenis simpanan" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                                 class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                                @foreach ($pilihanJenis as $nilai => $teks)
                                    <button type="button" role="menuitemradio" data-nilai="{{ $nilai }}" aria-checked="{{ (string) $nilai === (string) ($filterRiwayat['jenis_simpanan_id'] ?? '') ? 'true' : 'false' }}"
                                            x-on:click="buka = false; $refs.nilai.value = @js((string) $nilai); kirim()" class="{{ $kelasOpsi }}">
                                        {{ $teks }}
                                        <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dropdown waktu: "Pilih tanggal…" dipisah garis dan tidak mengirim form. --}}
                        <div class="relative" data-dropdown-riwayat="rentang" x-data="{ buka: false, label: @js($labelWaktu) }"
                             x-on:keydown.escape.window="if (buka) { buka = false; $refs.pemicu.focus() }"
                             x-on:click.outside="buka = false">
                            <input type="hidden" name="rentang" value="{{ $rentangAktif }}" x-bind:value="rentang">
                            <x-ui.button variant="secondary" size="sm" type="button" x-ref="pemicu" x-on:click="buka = ! buka"
                                         aria-label="Rentang waktu" aria-haspopup="menu" aria-controls="menu-riwayat-waktu" x-bind:aria-expanded="buka.toString()" aria-expanded="false">
                                <span data-dropdown-label x-text="label[rentang] ?? label['']">{{ $labelWaktu[$rentangAktif] }}</span>
                                <x-ui.icon name="chevron-down" class="h-4 w-4 text-stone-500" />
                            </x-ui.button>
                            <div id="menu-riwayat-waktu" role="menu" aria-label="Rentang waktu" x-cloak x-show="buka" x-transition.opacity.duration.100ms
                                 class="absolute right-0 z-10 mt-2 w-44 overflow-hidden rounded-xl border border-stone-200 bg-white py-1 shadow-lg">
                                @foreach ($pilihanWaktu as $nilai => $teks)
                                    <button type="button" role="menuitemradio" data-nilai="{{ $nilai }}" aria-checked="{{ (string) $nilai === $rentangAktif ? 'true' : 'false' }}"
                                            x-bind:aria-checked="(rentang === @js((string) $nilai)).toString()"
                                            x-on:click="buka = false; rentang = @js((string) $nilai); kirim()" class="{{ $kelasOpsi }}">
                                        {{ $teks }}
                                        <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                    </button>
                                @endforeach
                                <div role="separator" class="my-1 border-t border-stone-200"></div>
                                <button type="button" role="menuitemradio" data-nilai="kustom" aria-checked="{{ $rentangAktif === 'kustom' ? 'true' : 'false' }}"
                                        x-bind:aria-checked="(rentang === 'kustom').toString()"
                                        x-on:click="buka = false; rentang = 'kustom'; $nextTick(() => $refs.dari.focus())" class="{{ $kelasOpsi }}">
                                    Pilih tanggal…
                                    <span class="hidden h-1.5 w-1.5 rounded-full bg-arang group-aria-checked:block" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>

                        @if ($adaFilterRiwayat)
                            <a href="{{ $urlReset }}" data-reset-riwayat class="px-1 text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
                        @endif
                    </div>
                </div>

                {{-- Rentang kustom (D.4): kolom disabled saat rentang bukan kustom supaya tidak ikut terkirim (H.5.3). --}}
                <div data-rentang-tanggal x-show="rentang === 'kustom'" @if ($rentangAktif !== 'kustom') x-cloak @endif
                     class="flex flex-wrap items-center justify-end gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        Dari
                        <span class="block w-44">
                            <x-ui.input type="date" name="dari" value="{{ $filterRiwayat['dari'] }}" x-ref="dari"
                                        x-bind:disabled="rentang !== 'kustom'" x-on:change="cekTanggal()" :disabled="$rentangAktif !== 'kustom'" />
                        </span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        Sampai
                        <span class="block w-44">
                            <x-ui.input type="date" name="sampai" value="{{ $filterRiwayat['sampai'] }}" x-ref="sampai"
                                        x-bind:disabled="rentang !== 'kustom'" x-on:change="cekTanggal()" :disabled="$rentangAktif !== 'kustom'" />
                        </span>
                    </label>
                </div>

                <button type="submit" class="sr-only">Terapkan filter</button>
            </form>

            {{-- Ringkasan periode (D.4): satu query agregasi dengan filter yang sama. --}}
            <div class="border-b border-stone-200 px-6 py-2.5 text-sm text-stone-500" data-ringkasan-riwayat>
                Setor <span class="font-medium text-setor tabular-nums" data-ringkasan-setor>{{ Format::rupiah($ringkasanRiwayat['total_setor']) }}</span>
                <span class="mx-1.5 text-stone-300" aria-hidden="true">·</span>
                Tarik <span class="font-medium text-tarik tabular-nums" data-ringkasan-tarik>{{ Format::rupiah($ringkasanRiwayat['total_tarik']) }}</span>
            </div>

            @if ($riwayatTransaksi->isEmpty())
                <x-ui.empty icon="funnel" title="Tidak ada transaksi pada periode ini." data-kosong-filter>
                    <a href="{{ $urlReset }}" class="text-sm font-medium text-stone-600 hover:text-stone-900">Reset</a>
                </x-ui.empty>
            @else
                {{-- Kolom "Jenis simpanan" dihapus: nama jenis sudah tampil di sel Transaksi (keputusan F3). --}}
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Transaksi</th>
                                <th class="num">Jumlah</th>
                                <th class="num" title="Saldo jenis simpanan ini setelah transaksi">Saldo jenis ini</th>
                                <th>Dicatat oleh</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($riwayatTransaksi as $transaksi)
                                @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                                <tr data-transaksi="{{ $setor ? 'setor' : 'tarik' }}" data-id-transaksi="{{ $transaksi->id }}">
                                    <td class="whitespace-nowrap">{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                    <td><x-ui.jenis-transaksi :jenis="$transaksi->jenis_transaksi" :nama-jenis="$transaksi->jenisSimpanan->nama_jenis" /></td>
                                    <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-setor' : 'text-tarik' }}" data-nominal>{{ $setor ? '+' : '-' }}{{ Format::rupiah($transaksi->jumlah) }}</td>
                                    {{-- Nilai saldo_akhir dari database, tidak dihitung ulang dari hasil filter. --}}
                                    <td class="num whitespace-nowrap" data-saldo-jenis-ini>{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                                    <td>{{ $transaksi->staff->nama }}</td>
                                    <td class="text-stone-500" data-sel-catatan>@if (filled($transaksi->keterangan))<span title="{{ $transaksi->keterangan }}" data-catatan>{{ Str::limit($transaksi->keterangan, 40) }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($riwayatTransaksi->hasPages())
                    <div class="border-t border-stone-200 px-6 py-4">
                        {{ $riwayatTransaksi->links() }}
                    </div>
                @endif
            @endif
        @endif
    </x-ui.card>

    @if ($aktif)
        @include('anggota.partials.modal-nonaktifkan', ['anggota' => $anggota])
    @endif
</x-app-layout>

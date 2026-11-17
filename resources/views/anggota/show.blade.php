@php
    use App\Models\Anggota;
    use App\Models\TransaksiSimpanan;
    use App\Support\Format;
    use Illuminate\Support\Str;

    $aktif = $anggota->isAktif();
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
        <x-ui.card class="lg:col-span-2" data-kartu-saldo>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Saldo simpanan</h2>
            </x-slot:header>

            <p class="text-sm text-stone-500">Total saldo</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight text-stone-900 tabular-nums" data-total-saldo>{{ Format::rupiah($totalSaldo) }}</p>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                @foreach ($saldoPerJenis as $saldo)
                    <div class="rounded-xl border border-stone-200 bg-stone-50/60 p-4" data-saldo-jenis>
                        <p class="text-xs text-stone-500">{{ $saldo['nama_jenis'] }}</p>
                        <p class="mt-1 whitespace-nowrap text-lg font-semibold text-stone-900 tabular-nums">{{ Format::rupiah($saldo['saldo']) }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card data-kartu-identitas>
            <x-slot:header>
                <h2 class="text-base font-semibold text-stone-900">Identitas</h2>
            </x-slot:header>

            <dl class="space-y-4 text-sm">
                @foreach ([
                    'NIK' => $anggota->nik,
                    'No. telepon' => $anggota->no_telepon,
                    'Alamat' => $anggota->alamat,
                    'Bergabung' => Format::tanggal($anggota->tanggal_bergabung),
                ] as $label => $nilai)
                    <div>
                        <dt class="text-xs text-stone-500">{{ $label }}</dt>
                        <dd class="mt-0.5 text-stone-900 {{ $label === 'NIK' ? 'font-mono tabular-nums' : '' }}">{{ filled($nilai) ? $nilai : '–' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>

    <x-ui.card padding="none" class="mt-6" data-kartu-riwayat>
        <x-slot:header>
            <h2 class="text-base font-semibold text-stone-900">Riwayat transaksi</h2>
        </x-slot:header>

        @if ($riwayatTransaksi->isEmpty())
            <x-ui.empty icon="banknotes" title="Belum ada transaksi untuk anggota ini." />
        @else
            {{-- Kolom "Jenis simpanan" dihapus: nama jenis sudah tampil di sel Transaksi (keputusan F3). --}}
            <div class="overflow-x-auto">
                <table class="tabel">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Saldo akhir</th>
                            <th>Dicatat oleh</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayatTransaksi as $transaksi)
                            @php $setor = $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR; @endphp
                            <tr data-transaksi="{{ $setor ? 'setor' : 'tarik' }}">
                                <td class="whitespace-nowrap">{{ Format::tanggal($transaksi->tanggal_transaksi) }}</td>
                                <td><x-ui.jenis-transaksi :jenis="$transaksi->jenis_transaksi" :nama-jenis="$transaksi->jenisSimpanan->nama_jenis" /></td>
                                <td class="num whitespace-nowrap font-medium {{ $setor ? 'text-setor' : 'text-tarik' }}" data-nominal>{{ $setor ? '+' : '-' }}{{ Format::rupiah($transaksi->jumlah) }}</td>
                                <td class="num whitespace-nowrap">{{ Format::rupiah($transaksi->saldo_akhir) }}</td>
                                <td>{{ $transaksi->staff->nama }}</td>
                                <td class="text-stone-500">
                                    @if (filled($transaksi->keterangan))
                                        <span title="{{ $transaksi->keterangan }}" data-catatan>{{ Str::limit($transaksi->keterangan, 40) }}</span>
                                    @else
                                        –
                                    @endif
                                </td>
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
    </x-ui.card>

    @if ($aktif)
        @include('anggota.partials.modal-nonaktifkan', ['anggota' => $anggota])
    @endif
</x-app-layout>

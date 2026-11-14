{{-- PDF laporan per anggota (A4 landscape). Data: hasil LaporanService::perAnggota() + dicetak_oleh. --}}
@include('laporan.partials.pdf-kerangka', [
    'judul' => 'LAPORAN SIMPANAN PER ANGGOTA',
    'periode' => $periode['label'],
    'filter' => $filter_label,
    'dicetakPada' => $dicetak_pada,
    'dicetakOleh' => $dicetak_oleh,
    'isi' => 'laporan.partials.per-anggota-isi',
    'data' => ['jenis_simpanan' => $jenis_simpanan, 'anggota' => $anggota],
])

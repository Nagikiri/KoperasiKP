{{-- PDF laporan keseluruhan (A4 landscape). Data: hasil LaporanService::keseluruhan() + dicetak_oleh. --}}
@include('laporan.partials.pdf-kerangka', [
    'judul' => 'LAPORAN SIMPANAN KESELURUHAN',
    'periode' => $periode['label'],
    'filter' => $filter_label,
    'dicetakPada' => $dicetak_pada,
    'dicetakOleh' => $dicetak_oleh,
    'isi' => 'laporan.partials.keseluruhan-isi',
    'data' => [
        'ringkasan' => $ringkasan,
        'total' => $total,
        'saldo_anggota' => $saldo_anggota,
        'transaksi' => $transaksi,
    ],
])

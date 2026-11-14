<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LaporanService
{
    /**
     * Laporan per anggota (FR-16, BR-15, BR-16; revisi R7). Satu sumber data
     * untuk pratinjau, PDF, dan Excel.
     *
     * Filter (semua opsional): anggota_id (kosong = semua anggota),
     * status_anggota (hanya berlaku saat semua anggota), tanggal_mulai,
     * tanggal_selesai. Saldo dihitung per akhir periode (sampai tanggal_selesai,
     * atau sampai hari ini jika kosong).
     *
     * Query tetap: daftar jenis, daftar anggota, SATU agregasi saldo
     * (GROUP BY anggota_id, jenis_simpanan_id), SATU query transaksi (plus eager
     * load jenisSimpanan dan staff) yang dikelompokkan per anggota. Tanpa N+1.
     *
     * @param  array<string, string|null>  $filter
     * @return array{
     *     periode: array{mulai: ?Carbon, selesai: ?Carbon, label: string},
     *     filter_label: string,
     *     jenis_simpanan: array<int, string>,
     *     anggota: Collection<int, array{no_anggota: string, nama: string, status: string, tanggal_bergabung: Carbon, saldo_per_jenis: array<int, array{nama_jenis: string, saldo: float}>, total_saldo: float, transaksi: Collection<int, TransaksiSimpanan>, total_setor_periode: float, total_tarik_periode: float}>,
     *     dicetak_pada: Carbon
     * }
     */
    public function perAnggota(array $filter): array
    {
        $anggotaId = $filter['anggota_id'] ?? null;
        $status = $anggotaId === null ? ($filter['status_anggota'] ?? null) : null;
        $mulai = $filter['tanggal_mulai'] ?? null;
        $selesai = $filter['tanggal_selesai'] ?? null;

        $tanggalMulai = $mulai !== null ? Carbon::parse($mulai)->startOfDay() : null;
        $tanggalSelesai = $selesai !== null ? Carbon::parse($selesai)->startOfDay() : null;

        $jenisList = JenisSimpanan::query()->orderBy('id')->get();

        $cakupanAnggota = fn () => Anggota::query()
            ->when($anggotaId, fn ($query, $id) => $query->whereKey($id))
            ->when($status, fn ($query, $s) => $query->where('status', $s));

        $daftarAnggota = $cakupanAnggota()
            ->orderBy('no_anggota')
            ->get(['id', 'no_anggota', 'nama', 'status', 'tanggal_bergabung']);

        $saldoMap = [];
        TransaksiSimpanan::query()
            ->whereIn('anggota_id', $cakupanAnggota()->select('id'))
            ->when($selesai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '<=', $tanggal))
            ->selectRaw("anggota_id, jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS saldo")
            ->groupBy('anggota_id', 'jenis_simpanan_id')
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$saldoMap) {
                $saldoMap[(int) $baris->anggota_id][(int) $baris->jenis_simpanan_id] = round((float) $baris->saldo, 2);
            });

        $transaksiPerAnggota = TransaksiSimpanan::query()
            ->whereIn('anggota_id', $cakupanAnggota()->select('id'))
            ->when($mulai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '>=', $tanggal))
            ->when($selesai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '<=', $tanggal))
            ->with(['jenisSimpanan', 'staff'])
            ->orderBy('tanggal_transaksi')
            ->orderBy('id')
            ->get()
            ->groupBy('anggota_id');

        $anggota = $daftarAnggota
            ->map(function (Anggota $item) use ($jenisList, $saldoMap, $transaksiPerAnggota) {
                $transaksiItem = $transaksiPerAnggota->get($item->id, collect())->values();

                $perJenis = $jenisList->map(fn (JenisSimpanan $jenis) => [
                    'nama_jenis' => $jenis->nama_jenis,
                    'saldo' => $saldoMap[$item->id][$jenis->id] ?? 0.0,
                ])->all();

                return [
                    'no_anggota' => $item->no_anggota,
                    'nama' => $item->nama,
                    'status' => $item->status,
                    'tanggal_bergabung' => $item->tanggal_bergabung,
                    'saldo_per_jenis' => $perJenis,
                    'total_saldo' => round((float) array_sum(array_column($perJenis, 'saldo')), 2),
                    'transaksi' => $transaksiItem,
                    // Ringkasan periode per anggota (revisi R6), dari transaksi yang sudah dimuat.
                    'total_setor_periode' => round((float) $transaksiItem->where('jenis_transaksi', TransaksiSimpanan::SETOR)->sum(fn ($t) => (float) $t->jumlah), 2),
                    'total_tarik_periode' => round((float) $transaksiItem->where('jenis_transaksi', TransaksiSimpanan::TARIK)->sum(fn ($t) => (float) $t->jumlah), 2),
                ];
            })
            ->values();

        if ($anggotaId !== null) {
            $terpilih = $daftarAnggota->first();
            $filterLabel = 'Anggota: '.($terpilih ? $terpilih->no_anggota.' - '.$terpilih->nama : '-');
        } else {
            $filterLabel = 'Anggota: Semua; Status: '.($status !== null ? ucfirst($status) : 'Semua');
        }

        return [
            'periode' => [
                'mulai' => $tanggalMulai,
                'selesai' => $tanggalSelesai,
                'label' => $this->labelPeriode($tanggalMulai, $tanggalSelesai),
            ],
            'filter_label' => $filterLabel,
            'jenis_simpanan' => $jenisList->pluck('nama_jenis')->all(),
            'anggota' => $anggota,
            'dicetak_pada' => now(),
        ];
    }

    /**
     * Laporan keseluruhan simpanan (FR-21, BR-20). Semua filter opsional; tanpa
     * filter = seluruh data. Satu sumber data untuk pratinjau, PDF, dan Excel.
     *
     * Cakupan filter:
     * - tanggal_mulai/tanggal_selesai: periode total setor/tarik dan transaksi;
     *   tanggal_selesai juga menjadi batas saldo akhir periode.
     * - jenis_simpanan_id: ringkasan, kolom saldo anggota, dan transaksi.
     * - status_anggota: ringkasan, daftar saldo anggota, dan transaksi.
     * - jenis_transaksi: hanya daftar transaksi (saldo selalu setor - tarik).
     *
     * Dengan $perHalaman, `transaksi` berupa paginator (khusus pratinjau).
     *
     * @param  array<string, string|null>  $filter
     * @return array{
     *     periode: array{mulai: ?Carbon, selesai: ?Carbon, label: string},
     *     filter_label: string,
     *     ringkasan: Collection<int, array{nama_jenis: string, total_setor: float, total_tarik: float, saldo_akhir_periode: float}>,
     *     total: array{total_setor: float, total_tarik: float, saldo_akhir_periode: float},
     *     saldo_anggota: Collection<int, array{no_anggota: string, nama: string, status: string, tanggal_bergabung: Carbon, saldo_per_jenis: array<int, array{nama_jenis: string, saldo: float}>, total_saldo: float}>,
     *     transaksi: Collection<int, TransaksiSimpanan>|LengthAwarePaginator,
     *     dicetak_pada: Carbon
     * }
     */
    public function keseluruhan(array $filter, ?int $perHalaman = null): array
    {
        $mulai = $filter['tanggal_mulai'] ?? null;
        $selesai = $filter['tanggal_selesai'] ?? null;
        $jenisId = $filter['jenis_simpanan_id'] ?? null;
        $jenisTransaksi = $filter['jenis_transaksi'] ?? null;
        $status = $filter['status_anggota'] ?? null;

        $tanggalMulai = $mulai !== null ? Carbon::parse($mulai)->startOfDay() : null;
        $tanggalSelesai = $selesai !== null ? Carbon::parse($selesai)->startOfDay() : null;

        $jenisList = JenisSimpanan::query()
            ->when($jenisId, fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        $cakupan = fn () => TransaksiSimpanan::query()
            ->whereIn('jenis_simpanan_id', $jenisList->modelKeys())
            ->when($status, fn ($query, $s) => $query->whereIn(
                'anggota_id',
                Anggota::query()->select('id')->where('status', $s)
            ));

        // Total setor/tarik dalam periode, per jenis.
        $agregatPeriode = $cakupan()
            ->when($mulai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '>=', $tanggal))
            ->when($selesai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '<=', $tanggal))
            ->selectRaw("jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik")
            ->groupBy('jenis_simpanan_id')
            ->get()
            ->keyBy('jenis_simpanan_id');

        // Saldo per anggota per jenis per akhir periode: SATU query agregasi.
        $saldoMap = [];
        $cakupan()
            ->when($selesai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '<=', $tanggal))
            ->selectRaw("anggota_id, jenis_simpanan_id,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS saldo")
            ->groupBy('anggota_id', 'jenis_simpanan_id')
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$saldoMap) {
                $saldoMap[(int) $baris->anggota_id][(int) $baris->jenis_simpanan_id] = round((float) $baris->saldo, 2);
            });

        $saldoAnggota = Anggota::query()
            ->when($status, fn ($query, $s) => $query->where('status', $s))
            ->orderBy('no_anggota')
            ->get(['id', 'no_anggota', 'nama', 'status', 'tanggal_bergabung'])
            ->map(function (Anggota $anggota) use ($jenisList, $saldoMap) {
                $perJenis = $jenisList->map(fn (JenisSimpanan $jenis) => [
                    'nama_jenis' => $jenis->nama_jenis,
                    'saldo' => $saldoMap[$anggota->id][$jenis->id] ?? 0.0,
                ])->all();

                return [
                    'no_anggota' => $anggota->no_anggota,
                    'nama' => $anggota->nama,
                    'status' => $anggota->status,
                    'tanggal_bergabung' => $anggota->tanggal_bergabung,
                    'saldo_per_jenis' => $perJenis,
                    'total_saldo' => round((float) array_sum(array_column($perJenis, 'saldo')), 2),
                ];
            })
            ->values();

        $ringkasan = $jenisList
            ->map(function (JenisSimpanan $jenis) use ($agregatPeriode, $saldoMap) {
                $baris = $agregatPeriode->get($jenis->id);

                return [
                    'nama_jenis' => $jenis->nama_jenis,
                    'total_setor' => round((float) ($baris->total_setor ?? 0), 2),
                    'total_tarik' => round((float) ($baris->total_tarik ?? 0), 2),
                    'saldo_akhir_periode' => round((float) array_sum(array_column($saldoMap, $jenis->id)), 2),
                ];
            })
            ->values();

        $queryTransaksi = TransaksiSimpanan::query()
            ->with(['anggota', 'jenisSimpanan', 'staff'])
            ->when($mulai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '>=', $tanggal))
            ->when($selesai, fn ($query, $tanggal) => $query->whereDate('tanggal_transaksi', '<=', $tanggal))
            ->when($jenisId, fn ($query, $id) => $query->where('jenis_simpanan_id', $id))
            ->when($jenisTransaksi, fn ($query, $jenis) => $query->where('jenis_transaksi', $jenis))
            ->when($status, fn ($query, $s) => $query->whereIn(
                'anggota_id',
                Anggota::query()->select('id')->where('status', $s)
            ))
            ->orderBy('tanggal_transaksi')
            ->orderBy('id');

        return [
            'periode' => [
                'mulai' => $tanggalMulai,
                'selesai' => $tanggalSelesai,
                'label' => $this->labelPeriode($tanggalMulai, $tanggalSelesai),
            ],
            'filter_label' => implode('; ', [
                'Jenis: '.($jenisId !== null ? $jenisList->first()?->nama_jenis : 'Semua'),
                'Transaksi: '.($jenisTransaksi !== null ? ucfirst($jenisTransaksi) : 'Semua'),
                'Status: '.($status !== null ? ucfirst($status) : 'Semua'),
            ]),
            'ringkasan' => $ringkasan,
            'total' => [
                'total_setor' => round((float) $ringkasan->sum('total_setor'), 2),
                'total_tarik' => round((float) $ringkasan->sum('total_tarik'), 2),
                'saldo_akhir_periode' => round((float) $ringkasan->sum('saldo_akhir_periode'), 2),
            ],
            'saldo_anggota' => $saldoAnggota,
            'transaksi' => $perHalaman !== null
                ? $queryTransaksi->paginate($perHalaman)->withQueryString()
                : $queryTransaksi->get(),
            'dicetak_pada' => now(),
        ];
    }

    private function labelPeriode(?Carbon $mulai, ?Carbon $selesai): string
    {
        if ($mulai !== null && $selesai !== null) {
            return Format::tanggal($mulai).' s.d. '.Format::tanggal($selesai);
        }

        if ($mulai !== null) {
            return 'Sejak '.Format::tanggal($mulai);
        }

        if ($selesai !== null) {
            return 'Sampai '.Format::tanggal($selesai);
        }

        return 'Seluruh periode';
    }
}

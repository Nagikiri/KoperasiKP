{{--
    Kepala laporan seragam (revisi R2), dipakai kedua PDF dan kedua pratinjau.
    Area kop disiapkan untuk logo dan data koperasi (diisi di tahap frontend).
    Variabel: $judul, $periode (label), $filter (label).
--}}
<div class="lap-kepala">
    <div class="lap-kop" data-area-kop>
        <span class="lap-kop-nama">KSP Karya Duta Utama</span>
    </div>
    <p class="lap-judul">{{ $judul }}</p>
    <p class="lap-periode">Periode : {{ $periode }}</p>
    <p class="lap-periode">Filter : {{ $filter }}</p>
    <p class="lap-keterangan">Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama</p>
</div>

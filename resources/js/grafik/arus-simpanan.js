// Grafik dashboard (F2b D.3–D.4, dirapikan F2c C dan H): "Arus simpanan" (6 periode x 4 tampilan) dan donat
// "Sebaran simpanan". Dimuat lewat dynamic import dari app.js hanya jika #grafik-arus atau #grafik-komposisi ada,
// supaya Chart.js tidak masuk bundle halaman lain. Legenda, judul, dan warna bawaan Chart.js tidak dipakai.
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, LinearScale, LineController, LineElement, PointElement, Tooltip);

// Satu sumber warna (F2c H, F2d A). Token Tailwind setor/tarik memakai nilai yang sama.
const WARNA = {
    setor: '#3F63A8',
    tarik: '#B85A41',
    saldo: '#C8930E',
    // Gradasi isi area: warna seri -> transparan (F2c §0.2, H).
    isiSetor: [63, 99, 168, 0.22],
    isiTarik: [184, 90, 65, 0.18],
    isiSaldo: [212, 160, 23, 0.22],
    setorGabungan: 'rgba(63,99,168,0.9)',
    tarikGabungan: 'rgba(184,90,65,0.9)',
    pokok: '#1F2328',
    wajib: '#D4A017',
    sukarela: '#94A3B8',
    grid: '#E7E5E4',
    sumbu: '#78716C',
    garisDasar: '#D6D3D1',
    garisBantu: '#A8A29E',
    tooltip: '#1F2328',
};

const WARNA_JENIS = {
    'Simpanan Pokok': WARNA.pokok,
    'Simpanan Wajib': WARNA.wajib,
    'Simpanan Sukarela': WARNA.sukarela,
};

const PERIODE = ['minggu', 'bulan', 'tiga_bulan', 'enam_bulan', 'tahun', 'lima_tahun'];
const TAMPILAN = { garis: 'Garis', batang: 'Batang', saldo: 'Saldo', gabungan: 'Gabungan' };
const TAMPILAN_DEFAULT = 'garis';
const KUNCI_SIMPAN = { periode: 'dashboard.arus.periode', tampilan: 'dashboard.arus.tampilan' };

// Teks yang sama dengan dashboard.blade.php (F2d C.3, F2c C.3).
const TEKS_PERIODE = {
    minggu: '7 hari',
    bulan: '30 hari',
    tiga_bulan: '3 bulan',
    enam_bulan: '6 bulan',
    tahun: '1 tahun',
    lima_tahun: '5 tahun',
};
const KELAS_SELISIH = ['text-naik', 'text-turun', 'text-stone-900'];
const KOLOM_PERTAMA = { minggu: 'Tanggal', bulan: 'Tanggal', tiga_bulan: 'Minggu', enam_bulan: 'Bulan', tahun: 'Bulan', lima_tahun: 'Kuartal' };
// F2d H.3.3: nama kolom pertama saat tabel mengikuti ember batang.
const KOLOM_PERTAMA_BATANG = { bulan: 'Minggu', lima_tahun: 'Tahun' };
const BATAS_LABEL_BATANG = 13;
// F2d H.4.1/H.6.1: ruang atas khusus tampilan Batang (2 baris label) dan faktor skala.
const RUANG_ATAS_BATANG = 28;
const FAKTOR_ATAS_BATANG = 1.18;
const FAKTOR_ATAS = 1.08;

Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";

// ---------------------------------------------------------------
// Format angka. Tanda selalu ASCII: +Rp … / -Rp …, nol = Rp 0 (F1 §0).
// ---------------------------------------------------------------
const angkaLengkap = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const angkaRingkas = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

function rupiah(nilai) {
    return `${nilai < 0 ? '-' : ''}Rp ${angkaLengkap.format(Math.abs(nilai))}`;
}

function rupiahBertanda(nilai) {
    if (nilai > 0) return `+${rupiah(nilai)}`;
    if (nilai < 0) return rupiah(nilai);
    return 'Rp 0';
}

// Contoh: 250000 -> "250 rb"; 1200000 -> "1,2 jt"; 1500000000 -> "1,5 M".
function ringkas(nilai) {
    const mutlak = Math.abs(nilai);
    const tanda = nilai < 0 ? '-' : '';

    if (mutlak >= 1e9) return `${tanda}${angkaRingkas.format(mutlak / 1e9)} M`;
    if (mutlak >= 1e6) return `${tanda}${angkaRingkas.format(mutlak / 1e6)} jt`;
    if (mutlak >= 1e3) return `${tanda}${angkaRingkas.format(mutlak / 1e3)} rb`;

    return `${tanda}${angkaLengkap.format(mutlak)}`;
}

function rupiahRingkas(nilai) {
    return nilai < 0 ? `-Rp ${ringkas(-nilai)}` : `Rp ${ringkas(nilai)}`;
}

// ---------------------------------------------------------------
// Penyimpanan pilihan (localStorage dibungkus try/catch).
// ---------------------------------------------------------------
function baca(kunci, valid, cadangan) {
    try {
        const nilai = window.localStorage.getItem(kunci);
        return valid.includes(nilai) ? nilai : cadangan;
    } catch {
        return cadangan;
    }
}

function simpan(kunci, nilai) {
    try {
        window.localStorage.setItem(kunci, nilai);
    } catch {
        // Diabaikan: pilihan hanya kenyamanan.
    }
}

// ---------------------------------------------------------------
// Skala sumbu Y yang rapi: 5 tick, maksimum dibulatkan, ruang 8% di atas (F2c C.2).
// ---------------------------------------------------------------
function langkahRapi(kasar) {
    const pangkat = 10 ** Math.floor(Math.log10(kasar));
    const pecahan = kasar / pangkat;
    const rapi = [1, 2, 2.5, 5, 10].find((n) => pecahan <= n) ?? 10;
    return rapi * pangkat;
}

function skalaRapi(nilai, { mulaiNol, faktorAtas = FAKTOR_ATAS }) {
    const angka = nilai.filter((n) => Number.isFinite(n));
    const tertinggi = angka.length ? Math.max(...angka) : 0;
    const terendah = angka.length ? Math.min(...angka) : 0;

    let bawah = mulaiNol ? Math.min(0, terendah) : terendah * 0.97;
    let atas = tertinggi * faktorAtas;
    if (atas <= bawah) atas = bawah + (Math.abs(bawah) || 100000);

    let langkah = langkahRapi((atas - bawah) / 4);
    for (let i = 0; i < 10; i++) {
        const min = mulaiNol ? Math.min(0, Math.floor(bawah / langkah) * langkah) : Math.floor(bawah / langkah) * langkah;
        const max = Math.ceil(atas / langkah) * langkah;
        if ((max - min) / langkah <= 4) return { min, max, stepSize: langkah };
        langkah = langkahRapi(langkah * 1.01);
    }

    return { min: bawah, max: atas, stepSize: undefined };
}

// ---------------------------------------------------------------
// Bagian bersama Chart.js
// ---------------------------------------------------------------
// Tooltip mengikuti kursor, sedikit di atasnya.
Tooltip.positioners.kursor = function (elemen, posisi) {
    if (!elemen.length || !posisi) return false;
    return { x: posisi.x, y: posisi.y - 12 };
};

const tooltipDasar = {
    backgroundColor: WARNA.tooltip,
    titleColor: '#FFFFFF',
    bodyColor: '#FFFFFF',
    titleFont: { size: 12, weight: '600' },
    bodyFont: { size: 12 },
    cornerRadius: 10,
    padding: 12,
    caretSize: 0,
    // Titik bulat 8 px di awal tiap baris, bukan kotak warna bawaan.
    usePointStyle: true,
    boxWidth: 8,
    boxHeight: 8,
    boxPadding: 6,
    callbacks: {
        labelPointStyle: () => ({ pointStyle: 'circle', rotation: 0 }),
    },
};

// Garis bantu vertikal putus-putus di posisi yang disorot.
const pluginGarisBantu = {
    id: 'garisBantu',
    afterDatasetsDraw(chart) {
        const aktif = chart.tooltip?.getActiveElements?.() ?? [];
        if (!aktif.length) return;

        const x = chart.scales.x.getPixelForValue(aktif[0].index);
        const { top, bottom } = chart.chartArea;
        const { ctx } = chart;

        ctx.save();
        ctx.beginPath();
        ctx.setLineDash([4, 4]);
        ctx.lineWidth = 1;
        ctx.strokeStyle = WARNA.garisBantu;
        ctx.moveTo(x, top);
        ctx.lineTo(x, bottom);
        ctx.stroke();
        ctx.restore();
    },
};

// Isi area bergradasi vertikal; dihitung ulang dari chartArea saat render (termasuk saat ukuran berubah).
function gradasi([r, g, b, a]) {
    return (konteks) => {
        const { chart } = konteks;
        const { chartArea } = chart;
        if (!chartArea) return `rgba(${r},${g},${b},0)`;

        const isi = chart.ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        isi.addColorStop(0, `rgba(${r},${g},${b},${a})`);
        isi.addColorStop(1, `rgba(${r},${g},${b},0)`);
        return isi;
    };
}

// ---------------------------------------------------------------
// Grafik arus simpanan
// ---------------------------------------------------------------
function datasetGaris(kunci, label, warna, isi) {
    return {
        kunci,
        label,
        type: 'line',
        borderColor: warna,
        backgroundColor: gradasi(isi),
        borderWidth: 2.5,
        tension: 0.4,
        cubicInterpolationMode: 'monotone',
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: warna,
        pointHoverBorderColor: '#FFFFFF',
        pointHoverBorderWidth: 2,
        fill: 'origin',
    };
}

function datasetBatang(kunci, label, warna) {
    return {
        kunci,
        label,
        type: 'bar',
        backgroundColor: warna,
        hoverBackgroundColor: warna,
        borderRadius: { topLeft: 6, topRight: 6 },
        borderSkipped: false,
        // F2d B.4: batang lebih tebal.
        maxBarThickness: 24,
        categoryPercentage: 0.72,
        barPercentage: 0.86,
    };
}

function datasetSaldo(sumbu = 'y') {
    return {
        ...datasetGaris('saldo', 'Saldo', WARNA.saldo, WARNA.isiSaldo),
        fill: 'start',
        yAxisID: sumbu,
        order: 0,
    };
}

// Seri yang tidak digambar, hanya supaya barisnya tetap muncul di tooltip (sumbu tersembunyi).
function datasetTooltipSaja(kunci, label, warna) {
    return {
        kunci,
        label,
        type: 'line',
        borderColor: warna,
        backgroundColor: warna,
        showLine: false,
        pointRadius: 0,
        pointHoverRadius: 0,
        yAxisID: 'tersembunyi',
    };
}

function susunDataset(tampilan) {
    switch (tampilan) {
        case 'batang':
            return [datasetBatang('setor', 'Setoran', WARNA.setor), datasetBatang('tarik', 'Penarikan', WARNA.tarik)];
        case 'saldo':
            return [
                datasetTooltipSaja('setor', 'Setoran', WARNA.setor),
                datasetTooltipSaja('tarik', 'Penarikan', WARNA.tarik),
                datasetSaldo(),
            ];
        case 'gabungan':
            return [
                { ...datasetBatang('setor', 'Setoran', WARNA.setorGabungan), order: 1 },
                { ...datasetBatang('tarik', 'Penarikan', WARNA.tarikGabungan), order: 1 },
                { ...datasetSaldo('y1'), fill: false },
            ];
        default:
            return [
                datasetGaris('setor', 'Setoran', WARNA.setor, WARNA.isiSetor),
                datasetGaris('tarik', 'Penarikan', WARNA.tarik, WARNA.isiTarik),
            ];
    }
}

function sumbuY(tambahan = {}) {
    return {
        grid: { color: WARNA.grid, lineWidth: 1, drawTicks: false },
        border: { display: false, dash: [4, 4] },
        ticks: {
            padding: 8,
            color: WARNA.sumbu,
            font: { size: 12, weight: '500' },
            callback: (nilai) => rupiahRingkas(nilai),
        },
        ...tambahan,
    };
}

function pasangGrafikArus(kanvas) {
    const sumber = document.getElementById('data-grafik');
    if (!sumber) return;

    const data = JSON.parse(sumber.textContent);
    const kartu = kanvas.closest('[data-kartu-arus]') ?? document;
    const kotak = (kunci) => kartu.querySelector(`[data-arus-kotak="${kunci}"]`);
    const elemen = {
        saldo: kartu.querySelector('[data-arus-saldo]'),
        kotak: { setor: kotak('setor'), tarik: kotak('tarik'), selisih: kotak('selisih') },
        periodeTeks: [...kartu.querySelectorAll('[data-arus-periode-teks]')],
        persamaan: kartu.querySelector('[data-arus-persamaan]'),
        tabel: kartu.querySelector('[data-arus-tabel]'),
        kolomPertama: kartu.querySelector('[data-arus-kolom-pertama]'),
        labelTampilan: kartu.querySelector('[data-tampilan-label]'),
        tab: [...kartu.querySelectorAll('[data-periode]')],
        pilihan: [...kartu.querySelectorAll('[data-tampilan]')],
    };

    const keadaan = {
        periode: baca(KUNCI_SIMPAN.periode, PERIODE, PERIODE.includes(data.default) ? data.default : 'enam_bulan'),
        tampilan: baca(KUNCI_SIMPAN.tampilan, Object.keys(TAMPILAN), TAMPILAN_DEFAULT),
    };

    const periodeAktif = () => data.periode[keadaan.periode];
    const pakaiBatang = () => keadaan.tampilan === 'batang' || keadaan.tampilan === 'gabungan';
    // F2d B.2: Batang dan Gabungan memakai ember `batang` jika ada; Garis dan Saldo memakai ember biasa.
    const emberAktif = () => (pakaiBatang() && periodeAktif().batang) || periodeAktif();
    const deret = (kunci) => emberAktif()[kunci].map(Number);
    let grafik = null;

    // Label nilai ringkas di atas batang (tampilan Batang, ember ≤ 13). F2d H.4.1: label tidak boleh saling
    // timpa. (a) label yang lebih tinggi dinaikkan satu baris; (b) jika masih bertabrakan, font 10 px;
    // (c) jika masih, SEMUA label disembunyikan pada render itu (tooltip tetap ada).
    function susunLabel(chart, ukuran) {
        const { ctx } = chart;
        ctx.font = `500 ${ukuran}px ${Chart.defaults.font.family}`;
        const tinggi = ukuran;
        const baris = ukuran + 2;
        const perKelompok = new Map();

        chart.data.datasets.forEach((dataset, i) => {
            chart.getDatasetMeta(i).data.forEach((batang, j) => {
                const nilai = Number(dataset.data[j]);
                if (!(nilai > 0)) return;
                const teks = ringkas(nilai);
                const label = { teks, x: batang.x, y: batang.y - 4, lebar: ctx.measureText(teks).width };
                if (!perKelompok.has(j)) perKelompok.set(j, []);
                perKelompok.get(j).push(label);
            });
        });

        const bertabrakan = (a, b) =>
            Math.abs(a.x - b.x) < (a.lebar + b.lebar) / 2 + 2 && Math.abs(a.y - b.y) < tinggi;

        // (a) Dalam satu kelompok: naikkan label yang posisinya lebih tinggi satu baris.
        perKelompok.forEach((kelompok) => {
            if (kelompok.length === 2 && bertabrakan(kelompok[0], kelompok[1])) {
                const atas = kelompok[0].y <= kelompok[1].y ? kelompok[0] : kelompok[1];
                const bawah = atas === kelompok[0] ? kelompok[1] : kelompok[0];
                atas.y = Math.min(atas.y, bawah.y) - baris;
            }
        });

        // Cek ulang semua pasangan (termasuk kelompok tetangga).
        const semua = [...perKelompok.values()].flat();
        for (let i = 0; i < semua.length; i++) {
            for (let k = i + 1; k < semua.length; k++) {
                if (bertabrakan(semua[i], semua[k])) return null;
            }
        }

        return semua;
    }

    const pluginLabelBatang = {
        id: 'labelBatang',
        afterDatasetsDraw(chart) {
            if (keadaan.tampilan !== 'batang' || chart.data.labels.length > BATAS_LABEL_BATANG) return;

            const { ctx } = chart;
            ctx.save();
            const ukuran = [11, 10].find((n) => susunLabel(chart, n) !== null);
            if (ukuran !== undefined) {
                const label = susunLabel(chart, ukuran);
                ctx.fillStyle = WARNA.sumbu;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                label.forEach(({ teks, x, y }) => ctx.fillText(teks, x, y));
            }
            ctx.restore();
        },
    };

    function hitungSkala() {
        const setor = deret('setor');
        const tarik = deret('tarik');
        const saldo = deret('saldo');

        if (keadaan.tampilan === 'saldo') return { y: skalaRapi(saldo, { mulaiNol: false }) };

        const faktorAtas = keadaan.tampilan === 'batang' ? FAKTOR_ATAS_BATANG : FAKTOR_ATAS;
        const skala = { y: skalaRapi([...setor, ...tarik], { mulaiNol: true, faktorAtas }) };
        if (keadaan.tampilan === 'gabungan') skala.y1 = skalaRapi(saldo, { mulaiNol: false });
        return skala;
    }

    function terapkanSkala(scales) {
        Object.entries(hitungSkala()).forEach(([sumbu, { min, max, stepSize }]) => {
            scales[sumbu].min = min;
            scales[sumbu].max = max;
            scales[sumbu].ticks.stepSize = stepSize;
        });
    }

    function buatGrafik() {
        const ember = emberAktif();
        const datasets = susunDataset(keadaan.tampilan).map((dataset) => ({ ...dataset, data: deret(dataset.kunci) }));
        const adaBatang = pakaiBatang();

        const scales = {
            x: {
                offset: adaBatang,
                grid: { display: false },
                border: { display: true, color: WARNA.garisDasar },
                ticks: {
                    color: WARNA.sumbu,
                    font: { size: 12, weight: '500' },
                    padding: 10,
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 8,
                },
            },
            y: sumbuY(),
        };

        if (keadaan.tampilan === 'gabungan') {
            scales.y1 = sumbuY({ position: 'right', grid: { display: false, drawTicks: false } });
        }
        if (keadaan.tampilan === 'saldo') {
            scales.tersembunyi = { display: false, beginAtZero: true };
        }
        terapkanSkala(scales);

        grafik = new Chart(kanvas, {
            type: adaBatang ? 'bar' : 'line',
            data: { labels: ember.label, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                layout: { padding: { top: keadaan.tampilan === 'batang' ? RUANG_ATAS_BATANG : 8, right: 4 } },
                animation: { duration: 400, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                scales,
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        ...tooltipDasar,
                        position: 'kursor',
                        yAlign: 'bottom',
                        callbacks: {
                            ...tooltipDasar.callbacks,
                            title: (item) => emberAktif().label_lengkap[item[0].dataIndex] ?? '',
                            label: (konteks) => `${konteks.dataset.label} ${rupiah(konteks.parsed.y)}`,
                            labelColor: (konteks) => {
                                const warna = { setor: WARNA.setor, tarik: WARNA.tarik, saldo: WARNA.saldo }[konteks.dataset.kunci];
                                return { borderColor: warna, backgroundColor: warna };
                            },
                        },
                    },
                },
            },
            plugins: [pluginGarisBantu, pluginLabelBatang],
        });
    }

    // F2d I.1–I.2: tiga kotak (label, nominal, jumlah transaksi) dan baris persamaan mengikuti periode aktif.
    function perbaruiKotak(periode) {
        const { ringkasan } = periode;
        const isi = (wadah, pemilih, teks) => {
            const el = wadah?.querySelector(pemilih);
            if (el) el.textContent = teks;
            return el;
        };
        // Nominal dipecah "Rp" | angka (I.1.3): hanya boleh turun baris setelah "Rp".
        const isiNominal = (wadah, teks) => {
            const pisah = teks.indexOf(' ');
            isi(wadah, '[data-nilai-rp]', teks.slice(0, pisah));
            isi(wadah, '[data-nilai-angka]', teks.slice(pisah + 1));
            return wadah?.querySelector('[data-arus-nilai]');
        };

        elemen.periodeTeks.forEach((el) => {
            el.textContent = TEKS_PERIODE[keadaan.periode];
        });

        const jumlahSetor = Number(ringkasan.jumlah_setor);
        const jumlahTarik = Number(ringkasan.jumlah_tarik);
        isiNominal(elemen.kotak.setor, rupiah(Number(ringkasan.total_setor)));
        isi(elemen.kotak.setor, '[data-arus-jumlah]', `${jumlahSetor} transaksi`);
        isiNominal(elemen.kotak.tarik, rupiah(Number(ringkasan.total_tarik)));
        isi(elemen.kotak.tarik, '[data-arus-jumlah]', `${jumlahTarik} transaksi`);

        const bersih = Number(ringkasan.bersih);
        const nilai = isiNominal(elemen.kotak.selisih, rupiahBertanda(bersih));
        if (nilai) {
            nilai.classList.remove(...KELAS_SELISIH);
            nilai.classList.add(bersih > 0 ? 'text-naik' : bersih < 0 ? 'text-turun' : 'text-stone-900');
        }
        isi(elemen.kotak.selisih, '[data-arus-jumlah]', `${jumlahSetor + jumlahTarik} transaksi`);

        if (elemen.persamaan) {
            isi(elemen.persamaan, '[data-persamaan="saldo_awal"]', rupiah(Number(ringkasan.saldo_awal)));
            isi(elemen.persamaan, '[data-persamaan="total_setor"]', rupiah(Number(ringkasan.total_setor)));
            isi(elemen.persamaan, '[data-persamaan="total_tarik"]', rupiah(Number(ringkasan.total_tarik)));
            isi(elemen.persamaan, '[data-persamaan="saldo_akhir"]', rupiah(Number(ringkasan.saldo_akhir)));
        }
    }

    function perbaruiKepala() {
        const periode = periodeAktif();
        const { ringkasan } = periode;

        elemen.saldo.textContent = rupiah(ringkasan.saldo_akhir);
        perbaruiKotak(periode);

        elemen.tab.forEach((tombol) => {
            const aktif = tombol.dataset.periode === keadaan.periode;
            tombol.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            tombol.classList.toggle('bg-arang', aktif);
            tombol.classList.toggle('text-white', aktif);
            tombol.classList.toggle('text-stone-500', !aktif);
            tombol.classList.toggle('hover:bg-stone-100', !aktif);
        });

        elemen.labelTampilan.textContent = TAMPILAN[keadaan.tampilan];
        elemen.pilihan.forEach((pilihan) => {
            pilihan.setAttribute('aria-checked', pilihan.dataset.tampilan === keadaan.tampilan ? 'true' : 'false');
        });

        // Tabel data di kaki kartu: baris terbaru di atas (C.3), mengikuti ember yang tampil (F2d B.2).
        const ember = emberAktif();
        if (elemen.kolomPertama) {
            elemen.kolomPertama.textContent =
                ember !== periode ? KOLOM_PERTAMA_BATANG[keadaan.periode] : KOLOM_PERTAMA[keadaan.periode];
        }
        const baris = ember.label_lengkap
            .map((label, i) => {
                const tr = document.createElement('tr');
                [label, rupiah(ember.setor[i]), rupiah(ember.tarik[i]), rupiah(ember.saldo[i])].forEach((isi, kolom) => {
                    const td = document.createElement('td');
                    td.textContent = isi;
                    if (kolom > 0) td.className = 'num';
                    tr.append(td);
                });
                return tr;
            })
            .reverse();
        elemen.tabel?.replaceChildren(...baris);
    }

    function gantiPeriode(periode) {
        if (!PERIODE.includes(periode) || periode === keadaan.periode) return;

        keadaan.periode = periode;
        simpan(KUNCI_SIMPAN.periode, periode);

        // Data dianimasikan, grafik tidak dibuat ulang.
        grafik.data.labels = emberAktif().label;
        grafik.data.datasets.forEach((dataset) => {
            dataset.data = deret(dataset.kunci);
        });
        terapkanSkala(grafik.options.scales);
        grafik.update();
        perbaruiKepala();
    }

    function gantiTampilan(tampilan) {
        if (!(tampilan in TAMPILAN) || tampilan === keadaan.tampilan) return;

        keadaan.tampilan = tampilan;
        simpan(KUNCI_SIMPAN.tampilan, tampilan);

        grafik.destroy();
        buatGrafik();
        perbaruiKepala();
    }

    elemen.tab.forEach((tombol) => tombol.addEventListener('click', () => gantiPeriode(tombol.dataset.periode)));
    elemen.pilihan.forEach((pilihan) => pilihan.addEventListener('click', () => gantiTampilan(pilihan.dataset.tampilan)));

    buatGrafik();
    perbaruiKepala();
}

// ---------------------------------------------------------------
// Donat sebaran simpanan (total di tengah ditulis lewat HTML di atas kanvas)
// ---------------------------------------------------------------
function pasangGrafikKomposisi(kanvas) {
    const sumber = document.getElementById('data-komposisi');
    if (!sumber) return;

    const data = JSON.parse(sumber.textContent);
    const warna = data.map((item) => WARNA_JENIS[item.nama_jenis] ?? WARNA.sukarela);
    const saldo = data.map((item) => Math.max(0, Number(item.saldo)));
    const total = saldo.reduce((a, b) => a + b, 0);
    const nama = data.map((item) => item.nama_jenis.replace('Simpanan ', ''));

    // F2d D.1: isi tengah donat. Default = jenis terbesar (dirender Blade); hover irisan/baris menggantinya.
    const kartu = kanvas.closest('[data-kartu-komposisi]') ?? document;
    const tengah = kartu.querySelector('[data-komposisi-tengah]');
    const baris = [...kartu.querySelectorAll('[data-komposisi-jenis]')];
    const angka1 = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    let aktif = null;

    function tampilkanTengah(indeks) {
        if (!tengah || indeks === aktif) return;
        aktif = indeks;
        const persenEl = tengah.querySelector('[data-tengah-persen]');
        const namaEl = tengah.querySelector('[data-tengah-nama]');
        if (indeks === null) {
            persenEl.textContent = tengah.dataset.defaultPersen;
            namaEl.textContent = tengah.dataset.defaultNama;
        } else {
            persenEl.textContent = `${angka1.format(total > 0 ? (saldo[indeks] / total) * 100 : 0)}%`;
            namaEl.textContent = nama[indeks];
        }
        baris.forEach((li) => li.classList.toggle('bg-stone-50', indeks !== null && Number(li.dataset.komposisiJenis) === indeks));
    }

    const grafik = new Chart(kanvas, {
        type: 'doughnut',
        data: {
            labels: nama,
            datasets: [
                {
                    data: saldo,
                    backgroundColor: warna,
                    hoverBackgroundColor: warna,
                    hoverOffset: 4,
                    borderWidth: 0,
                    // F2d D.2: sedikit lebih ramping.
                    spacing: 2,
                    borderRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
            cutout: '76%',
            animation: { duration: 400, easing: 'easeOutQuart' },
            onHover: (_event, elemen) => tampilkanTengah(elemen.length ? elemen[0].index : null),
            plugins: {
                legend: { display: false },
                title: { display: false },
                // Tooltip dimatikan: tengah donat sudah menjelaskan (D.1).
                tooltip: { enabled: false },
            },
        },
    });

    kanvas.addEventListener('mouseleave', () => tampilkanTengah(null));

    // Hover baris daftar menyorot irisannya dan mengganti isi tengah.
    baris.forEach((li) => {
        const indeks = Number(li.dataset.komposisiJenis);
        li.addEventListener('mouseenter', () => {
            if (saldo[indeks] > 0) {
                grafik.setActiveElements([{ datasetIndex: 0, index: indeks }]);
                grafik.update();
            }
            tampilkanTengah(indeks);
        });
        li.addEventListener('mouseleave', () => {
            grafik.setActiveElements([]);
            grafik.update();
            tampilkanTengah(null);
        });
    });
}

export default function pasangGrafikDashboard() {
    const arus = document.getElementById('grafik-arus');
    if (arus) pasangGrafikArus(arus);

    const komposisi = document.getElementById('grafik-komposisi');
    if (komposisi) pasangGrafikKomposisi(komposisi);
}

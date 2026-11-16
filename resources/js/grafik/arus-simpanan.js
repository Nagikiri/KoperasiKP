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

// Satu sumber warna (F2c H). Token Tailwind setor/tarik memakai nilai yang sama.
const WARNA = {
    setor: '#4F6BED',
    tarik: '#F0705A',
    saldo: '#C8930E',
    // Gradasi isi area: warna seri -> transparan (F2c §0.2, H).
    isiSetor: [79, 107, 237, 0.22],
    isiTarik: [240, 112, 90, 0.18],
    isiSaldo: [212, 160, 23, 0.22],
    setorGabungan: 'rgba(79,107,237,0.9)',
    tarikGabungan: 'rgba(240,112,90,0.9)',
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

// Teks yang sama dengan dashboard.blade.php (F2c C.1, C.3).
const DALAM_PERIODE = {
    minggu: 'dalam 7 hari',
    bulan: 'dalam 30 hari',
    tiga_bulan: 'dalam 3 bulan',
    enam_bulan: 'dalam 6 bulan',
    tahun: 'dalam setahun',
    lima_tahun: 'dalam 5 tahun',
};
const KOLOM_PERTAMA = { minggu: 'Tanggal', bulan: 'Tanggal', tiga_bulan: 'Minggu', enam_bulan: 'Bulan', tahun: 'Bulan', lima_tahun: 'Kuartal' };
const BATAS_LABEL_BATANG = 12;

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

function skalaRapi(nilai, { mulaiNol }) {
    const angka = nilai.filter((n) => Number.isFinite(n));
    const tertinggi = angka.length ? Math.max(...angka) : 0;
    const terendah = angka.length ? Math.min(...angka) : 0;

    let bawah = mulaiNol ? Math.min(0, terendah) : terendah * 0.97;
    let atas = tertinggi * 1.08;
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
        maxBarThickness: 14,
        categoryPercentage: 0.62,
        barPercentage: 0.78,
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
    const elemen = {
        saldo: kartu.querySelector('[data-arus-saldo]'),
        chip: kartu.querySelector('[data-arus-chip]'),
        setor: kartu.querySelector('[data-arus-setor]'),
        tarik: kartu.querySelector('[data-arus-tarik]'),
        legendaSaldo: kartu.querySelector('[data-arus-legenda-saldo]'),
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
    const deret = (kunci) => periodeAktif()[kunci].map(Number);
    let grafik = null;

    // Label nilai ringkas di atas batang (hanya tampilan Batang dengan ember ≤ 12).
    const pluginLabelBatang = {
        id: 'labelBatang',
        afterDatasetsDraw(chart) {
            if (keadaan.tampilan !== 'batang' || chart.data.labels.length > BATAS_LABEL_BATANG) return;

            const { ctx } = chart;
            ctx.save();
            ctx.font = `500 11px ${Chart.defaults.font.family}`;
            ctx.fillStyle = WARNA.sumbu;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            chart.data.datasets.forEach((dataset, i) => {
                chart.getDatasetMeta(i).data.forEach((batang, j) => {
                    const nilai = Number(dataset.data[j]);
                    if (nilai > 0) ctx.fillText(ringkas(nilai), batang.x, batang.y - 4);
                });
            });
            ctx.restore();
        },
    };

    function hitungSkala() {
        const setor = deret('setor');
        const tarik = deret('tarik');
        const saldo = deret('saldo');

        if (keadaan.tampilan === 'saldo') return { y: skalaRapi(saldo, { mulaiNol: false }) };

        const skala = { y: skalaRapi([...setor, ...tarik], { mulaiNol: true }) };
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
        const periode = periodeAktif();
        const datasets = susunDataset(keadaan.tampilan).map((dataset) => ({ ...dataset, data: deret(dataset.kunci) }));
        const adaBatang = keadaan.tampilan === 'batang' || keadaan.tampilan === 'gabungan';

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
            data: { labels: periode.label, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
                layout: { padding: { top: 8, right: 4 } },
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
                            title: (item) => periodeAktif().label_lengkap[item[0].dataIndex] ?? '',
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

    function perbaruiKepala() {
        const periode = periodeAktif();
        const { ringkasan } = periode;
        const nada = ringkasan.bersih > 0 ? 'setor' : ringkasan.bersih < 0 ? 'tarik' : 'nol';
        const kelasChip = {
            setor: ['bg-setor-50', 'text-setor'],
            tarik: ['bg-tarik-50', 'text-tarik'],
            nol: ['bg-stone-100', 'text-stone-500'],
        };

        elemen.saldo.textContent = rupiah(ringkasan.saldo_akhir);
        elemen.chip.textContent = ringkasan.bersih === 0 ? 'Tidak berubah' : `${rupiahBertanda(ringkasan.bersih)} ${DALAM_PERIODE[keadaan.periode]}`;
        elemen.chip.classList.remove(...Object.values(kelasChip).flat());
        elemen.chip.classList.add(...kelasChip[nada]);
        elemen.setor.textContent = rupiah(ringkasan.total_setor);
        elemen.tarik.textContent = rupiah(ringkasan.total_tarik);
        elemen.legendaSaldo.hidden = !(keadaan.tampilan === 'saldo' || keadaan.tampilan === 'gabungan');

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

        // Tabel data di kaki kartu: baris terbaru di atas (C.3).
        if (elemen.kolomPertama) elemen.kolomPertama.textContent = KOLOM_PERTAMA[keadaan.periode];
        const baris = periode.label_lengkap
            .map((label, i) => {
                const tr = document.createElement('tr');
                [label, rupiah(periode.setor[i]), rupiah(periode.tarik[i]), rupiah(periode.saldo[i])].forEach((isi, kolom) => {
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
        grafik.data.labels = periodeAktif().label;
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

    new Chart(kanvas, {
        type: 'doughnut',
        data: {
            labels: data.map((item) => item.nama_jenis.replace('Simpanan ', '')),
            datasets: [
                {
                    data: data.map((item) => Math.max(0, Number(item.saldo))),
                    backgroundColor: warna,
                    hoverBackgroundColor: warna,
                    borderWidth: 0,
                    spacing: 3,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: Math.max(window.devicePixelRatio || 1, 2),
            cutout: '72%',
            animation: { duration: 400, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                title: { display: false },
                tooltip: {
                    ...tooltipDasar,
                    callbacks: {
                        ...tooltipDasar.callbacks,
                        label: (konteks) => `${konteks.label} ${rupiah(konteks.parsed)}`,
                    },
                },
            },
        },
    });
}

export default function pasangGrafikDashboard() {
    const arus = document.getElementById('grafik-arus');
    if (arus) pasangGrafikArus(arus);

    const komposisi = document.getElementById('grafik-komposisi');
    if (komposisi) pasangGrafikKomposisi(komposisi);
}

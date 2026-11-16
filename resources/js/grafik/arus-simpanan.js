// Grafik dashboard (F2b D.3, D.4): "Arus simpanan" (6 periode x 4 tampilan) dan donat "Komposisi saldo".
// Dimuat lewat dynamic import dari app.js hanya jika #grafik-arus atau #grafik-komposisi ada,
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

// Satu sumber warna (F2b B). Token Tailwind setor/tarik memakai nilai yang sama.
const WARNA = {
    setor: '#2F7D6B',
    setorRedup: 'rgba(47,125,107,0.85)',
    tarik: '#B5503F',
    tarikRedup: 'rgba(181,80,63,0.85)',
    saldo: '#D4A017',
    saldoIsi: 'rgba(212,160,23,0.12)',
    pokok: '#1F2328',
    wajib: '#D4A017',
    sukarela: '#A8A29E',
    grid: '#EEEDEB',
    sumbu: '#A8A29E',
    tooltip: '#1F2328',
    garisBantu: '#D6D3D1',
};

const WARNA_JENIS = {
    'Simpanan Pokok': WARNA.pokok,
    'Simpanan Wajib': WARNA.wajib,
    'Simpanan Sukarela': WARNA.sukarela,
};

const PERIODE = ['minggu', 'bulan', 'tiga_bulan', 'enam_bulan', 'tahun', 'lima_tahun'];
const TAMPILAN = { garis: 'Garis', batang: 'Batang', saldo: 'Saldo', kombinasi: 'Kombinasi' };
const TAMPILAN_DEFAULT = 'garis';
const KUNCI_SIMPAN = { periode: 'dashboard.arus.periode', tampilan: 'dashboard.arus.tampilan' };

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

// Contoh: 250000 -> "Rp 250 rb"; 1200000 -> "Rp 1,2 jt"; 1500000000 -> "Rp 1,5 M".
function rupiahRingkas(nilai) {
    const mutlak = Math.abs(nilai);
    const tanda = nilai < 0 ? '-' : '';

    if (mutlak >= 1e9) return `${tanda}Rp ${angkaRingkas.format(mutlak / 1e9)} M`;
    if (mutlak >= 1e6) return `${tanda}Rp ${angkaRingkas.format(mutlak / 1e6)} jt`;
    if (mutlak >= 1e3) return `${tanda}Rp ${angkaRingkas.format(mutlak / 1e3)} rb`;

    return rupiah(nilai);
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
// Bagian bersama Chart.js
// ---------------------------------------------------------------
const tooltipDasar = {
    backgroundColor: WARNA.tooltip,
    titleColor: '#FFFFFF',
    bodyColor: '#FFFFFF',
    titleFont: { size: 12, weight: '600' },
    bodyFont: { size: 12 },
    cornerRadius: 8,
    padding: 10,
    // Titik berwarna di label, bukan kotak warna bawaan.
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

// ---------------------------------------------------------------
// Grafik arus simpanan
// ---------------------------------------------------------------
function datasetGaris(kunci, label, warna) {
    return {
        kunci,
        label,
        type: 'line',
        borderColor: warna,
        backgroundColor: warna,
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: warna,
        pointHoverBorderColor: '#FFFFFF',
        pointHoverBorderWidth: 2,
        fill: false,
    };
}

function datasetBatang(kunci, label, warna) {
    return {
        kunci,
        label,
        type: 'bar',
        backgroundColor: warna,
        hoverBackgroundColor: warna,
        borderRadius: 6,
        maxBarThickness: 16,
        categoryPercentage: 0.6,
        barPercentage: 0.8,
    };
}

function datasetSaldo(sumbu = 'y') {
    return {
        ...datasetGaris('saldo', 'Saldo', WARNA.saldo),
        backgroundColor: WARNA.saldoIsi,
        fill: 'origin',
        yAxisID: sumbu,
        order: 0,
    };
}

function susunDataset(tampilan) {
    switch (tampilan) {
        case 'batang':
            return [datasetBatang('setor', 'Setor', WARNA.setor), datasetBatang('tarik', 'Tarik', WARNA.tarik)];
        case 'saldo':
            // Setor/tarik tidak digambar, hanya supaya barisnya tetap muncul di tooltip.
            return [
                { ...datasetGaris('setor', 'Setor', WARNA.setor), showLine: false, pointHoverRadius: 0 },
                { ...datasetGaris('tarik', 'Tarik', WARNA.tarik), showLine: false, pointHoverRadius: 0 },
                datasetSaldo(),
            ];
        case 'kombinasi':
            return [
                { ...datasetBatang('setor', 'Setor', WARNA.setorRedup), order: 1 },
                { ...datasetBatang('tarik', 'Tarik', WARNA.tarikRedup), order: 1 },
                { ...datasetSaldo('y1'), fill: false, backgroundColor: WARNA.saldo },
            ];
        default:
            return [datasetGaris('setor', 'Setor', WARNA.setor), datasetGaris('tarik', 'Tarik', WARNA.tarik)];
    }
}

function sumbuY(tambahan = {}) {
    return {
        beginAtZero: true,
        grid: { color: WARNA.grid, drawTicks: false },
        border: { display: false },
        ticks: {
            maxTicksLimit: 5,
            padding: 8,
            color: WARNA.sumbu,
            font: { size: 12 },
            callback: (nilai) => rupiahRingkas(nilai),
        },
        ...tambahan,
    };
}

function batasTickX(periode) {
    return periode === 'bulan' || periode === 'lima_tahun' ? 8 : undefined;
}

function pasangGrafikArus(kanvas) {
    const sumber = document.getElementById('data-grafik');
    if (!sumber) return;

    const data = JSON.parse(sumber.textContent);
    const kartu = kanvas.closest('[data-kartu-arus]') ?? document;
    const elemen = {
        nama: kartu.querySelector('[data-arus-nama]'),
        saldo: kartu.querySelector('[data-arus-saldo]'),
        chip: kartu.querySelector('[data-arus-chip]'),
        setor: kartu.querySelector('[data-arus-setor]'),
        tarik: kartu.querySelector('[data-arus-tarik]'),
        legendaSaldo: kartu.querySelector('[data-arus-legenda-saldo]'),
        tabel: kartu.querySelector('[data-arus-tabel]'),
        labelTampilan: kartu.querySelector('[data-tampilan-label]'),
        tab: [...kartu.querySelectorAll('[data-periode]')],
        pilihan: [...kartu.querySelectorAll('[data-tampilan]')],
    };

    const keadaan = {
        periode: baca(KUNCI_SIMPAN.periode, PERIODE, PERIODE.includes(data.default) ? data.default : 'enam_bulan'),
        tampilan: baca(KUNCI_SIMPAN.tampilan, Object.keys(TAMPILAN), TAMPILAN_DEFAULT),
    };

    const periodeAktif = () => data.periode[keadaan.periode];
    let grafik = null;

    function isiDataset(dataset) {
        return periodeAktif()[dataset.kunci].map(Number);
    }

    function buatGrafik() {
        const periode = periodeAktif();
        const datasets = susunDataset(keadaan.tampilan).map((dataset) => ({ ...dataset, data: isiDataset(dataset) }));
        const adaBatang = keadaan.tampilan === 'batang' || keadaan.tampilan === 'kombinasi';

        const scales = {
            x: {
                offset: adaBatang,
                grid: { display: false },
                border: { display: false },
                ticks: {
                    color: WARNA.sumbu,
                    font: { size: 12 },
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: batasTickX(keadaan.periode),
                },
            },
            y: sumbuY(),
        };

        if (keadaan.tampilan === 'kombinasi') {
            scales.y1 = sumbuY({ position: 'right', grid: { display: false, drawTicks: false } });
        }

        grafik = new Chart(kanvas, {
            type: adaBatang ? 'bar' : 'line',
            data: { labels: periode.label, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                scales,
                plugins: {
                    legend: { display: false },
                    title: { display: false },
                    tooltip: {
                        ...tooltipDasar,
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
            plugins: [pluginGarisBantu],
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

        elemen.nama.textContent = periode.nama;
        elemen.saldo.textContent = rupiah(ringkasan.saldo_akhir);
        elemen.chip.textContent = rupiahBertanda(ringkasan.bersih);
        elemen.chip.classList.remove(...Object.values(kelasChip).flat());
        elemen.chip.classList.add(...kelasChip[nada]);
        elemen.setor.textContent = rupiah(ringkasan.total_setor);
        elemen.tarik.textContent = rupiah(ringkasan.total_tarik);
        elemen.legendaSaldo.hidden = !(keadaan.tampilan === 'saldo' || keadaan.tampilan === 'kombinasi');

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

        // Tabel "Lihat angka" (alternatif aksesibilitas).
        const baris = periode.label_lengkap.map((label, i) => {
            const tr = document.createElement('tr');
            [label, rupiah(periode.setor[i]), rupiah(periode.tarik[i]), rupiah(periode.saldo[i])].forEach((isi, kolom) => {
                const td = document.createElement('td');
                td.textContent = isi;
                if (kolom > 0) td.className = 'num';
                tr.append(td);
            });
            return tr;
        });
        elemen.tabel.replaceChildren(...baris);
    }

    function gantiPeriode(periode) {
        if (!PERIODE.includes(periode) || periode === keadaan.periode) return;

        keadaan.periode = periode;
        simpan(KUNCI_SIMPAN.periode, periode);

        grafik.data.labels = periodeAktif().label;
        grafik.data.datasets.forEach((dataset) => {
            dataset.data = isiDataset(dataset);
        });
        grafik.options.scales.x.ticks.maxTicksLimit = batasTickX(periode);
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
// Donat komposisi saldo (total di tengah ditulis lewat HTML di atas kanvas)
// ---------------------------------------------------------------
function pasangGrafikKomposisi(kanvas) {
    const sumber = document.getElementById('data-komposisi');
    if (!sumber) return;

    const data = JSON.parse(sumber.textContent);

    new Chart(kanvas, {
        type: 'doughnut',
        data: {
            labels: data.map((item) => item.nama_jenis),
            datasets: [
                {
                    data: data.map((item) => Math.max(0, Number(item.saldo))),
                    backgroundColor: data.map((item) => WARNA_JENIS[item.nama_jenis] ?? WARNA.sukarela),
                    hoverBackgroundColor: data.map((item) => WARNA_JENIS[item.nama_jenis] ?? WARNA.sukarela),
                    borderWidth: 0,
                    spacing: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            animation: { duration: 300 },
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

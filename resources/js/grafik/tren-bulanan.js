// Grafik tren 6 bulan di dashboard (F2). Dimuat lewat dynamic import dari app.js
// hanya jika elemen #grafik-tren ada, supaya Chart.js tidak masuk bundle halaman lain.
import { BarController, BarElement, CategoryScale, Chart, Legend, LinearScale, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const rupiahLengkap = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const angkaRingkas = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

function rupiah(nilai) {
    return `Rp ${rupiahLengkap.format(nilai)}`;
}

// Contoh: 1200000 -> "Rp 1,2 jt"; 1500000000 -> "Rp 1,5 M"; 250000 -> "Rp 250 rb".
function rupiahRingkas(nilai) {
    const mutlak = Math.abs(nilai);

    if (mutlak >= 1e9) return `Rp ${angkaRingkas.format(nilai / 1e9)} M`;
    if (mutlak >= 1e6) return `Rp ${angkaRingkas.format(nilai / 1e6)} jt`;
    if (mutlak >= 1e3) return `Rp ${angkaRingkas.format(nilai / 1e3)} rb`;

    return rupiah(nilai);
}

export default function pasangGrafikTren(kanvas) {
    const sumber = document.getElementById('data-tren');
    if (!sumber) return;

    const data = JSON.parse(sumber.textContent);

    new Chart(kanvas, {
        type: 'bar',
        data: {
            labels: data.map((item) => item.bulan),
            datasets: [
                {
                    label: 'Setor',
                    data: data.map((item) => Number(item.total_setor)),
                    backgroundColor: '#047857',
                    borderRadius: 6,
                    maxBarThickness: 28,
                },
                {
                    label: 'Tarik',
                    data: data.map((item) => Number(item.total_tarik)),
                    backgroundColor: '#B91C1C',
                    borderRadius: 6,
                    maxBarThickness: 28,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 300 },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8 },
                },
                tooltip: {
                    callbacks: {
                        label: (konteks) => `${konteks.dataset.label}: ${rupiah(konteks.parsed.y)}`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#E7E5E4' },
                    border: { display: false },
                    ticks: { callback: (nilai) => rupiahRingkas(nilai) },
                },
            },
        },
    });
}

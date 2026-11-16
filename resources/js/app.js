import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Grafik tren dashboard (F2): Chart.js hanya dimuat jika elemennya ada.
const grafikTren = document.getElementById('grafik-tren');
if (grafikTren) {
    import('./grafik/tren-bulanan').then(({ default: pasangGrafikTren }) => pasangGrafikTren(grafikTren));
}

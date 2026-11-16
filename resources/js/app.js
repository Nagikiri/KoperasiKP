import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Grafik dashboard (F2b D.3, D.4): Chart.js hanya dimuat jika salah satu kanvasnya ada.
if (document.getElementById('grafik-arus') || document.getElementById('grafik-komposisi')) {
    import('./grafik/arus-simpanan').then(({ default: pasangGrafikDashboard }) => pasangGrafikDashboard());
}

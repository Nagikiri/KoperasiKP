import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Grafik dashboard (F2b D.3, D.4): Chart.js hanya dimuat jika salah satu kanvasnya ada.
if (document.getElementById('grafik-arus') || document.getElementById('grafik-komposisi')) {
    import('./grafik/arus-simpanan').then(({ default: pasangGrafikDashboard }) => pasangGrafikDashboard());
}

// Isi cepat nominal di form setor/tarik (F4b A.4): klik MENGGANTI isi #jumlah, memicu event input (format ribuan
// dari partial format-jumlah ikut jalan), lalu fokus ke input. Tombol yang nilainya sama dengan isi input diberi
// border-arang. Tombol "Semua saldo" (data-isi-cepat="semua") mengambil nilainya dari data-nilai (diisi transaksi-tarik.js).
function pasangIsiCepat() {
    const jumlah = document.getElementById('jumlah');
    const tombol = [...document.querySelectorAll('[data-isi-cepat]')];

    if (!jumlah || tombol.length === 0) {
        return;
    }

    const angka = () => jumlah.value.split(',')[0].replace(/\D/g, '');
    const nilaiTombol = (el) => (el.dataset.isiCepat === 'semua' ? el.dataset.nilai ?? '' : el.dataset.isiCepat);

    const tandai = () => {
        const isi = angka();

        tombol.forEach((el) => {
            const aktif = isi !== '' && nilaiTombol(el) === isi;
            el.classList.toggle('border-arang', aktif);
            el.classList.toggle('border-stone-300', !aktif);
            el.setAttribute('aria-pressed', aktif ? 'true' : 'false');
        });
    };

    tombol.forEach((el) =>
        el.addEventListener('click', () => {
            if (el.disabled || nilaiTombol(el) === '') {
                return;
            }

            jumlah.value = nilaiTombol(el);
            jumlah.dispatchEvent(new Event('input', { bubbles: true }));
            jumlah.focus();
        }),
    );

    jumlah.addEventListener('input', tandai);
    document.addEventListener('isi-cepat:segarkan', tandai);
    tandai();
}

pasangIsiCepat();

// Saldo tersedia di form tarik (F4 §2, H.2.4): hanya dimuat jika data saldo ada di halaman.
if (document.getElementById('data-saldo')) {
    import('./transaksi-tarik').then(({ default: pasangSaldoTarik }) => pasangSaldoTarik());
}

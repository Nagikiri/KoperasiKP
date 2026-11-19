/*
 * Saldo sukarela tersedia di form tarik (F4 §2, H.2.4). Hanya tampilan: validasi saldo tetap di server.
 * Data: <script type="application/json" id="data-saldo"> berisi { id anggota aktif: saldo }. Anggota tanpa
 * transaksi Sukarela tidak ada di peta (dibaca 0). Peta kosong dikirim sebagai [] dan tetap aman.
 */
const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); // setara Format::rupiah

export default function pasangSaldoTarik() {
    const elemenData = document.getElementById('data-saldo');
    const pilihAnggota = document.getElementById('anggota_id');
    const kotak = document.querySelector('[data-kotak-saldo]');
    const nilai = document.querySelector('[data-nilai-saldo]');
    const jumlah = document.getElementById('jumlah');
    const peringatan = document.querySelector('[data-peringatan-saldo]');

    if (!elemenData || !pilihAnggota || !kotak || !nilai) {
        return;
    }

    let data = {};
    try {
        data = JSON.parse(elemenData.textContent) || {};
    } catch {
        data = {};
    }

    const saldoTerpilih = () => (pilihAnggota.value === '' ? null : Number(data[pilihAnggota.value] ?? 0));

    // Angka jumlah setelah titik pemisah ribuan dihapus; bagian setelah koma diabaikan.
    const angkaJumlah = () => {
        const angka = (jumlah?.value ?? '').split(',')[0].replace(/\D/g, '');

        return angka === '' ? null : Number(angka);
    };

    function perbarui() {
        const saldo = saldoTerpilih();

        kotak.hidden = saldo === null;
        nilai.textContent = saldo === null ? '' : rupiah(saldo);

        if (peringatan) {
            const diketik = angkaJumlah();
            peringatan.hidden = !(saldo !== null && diketik !== null && diketik > saldo);
        }
    }

    pilihAnggota.addEventListener('change', perbarui);
    jumlah?.addEventListener('input', perbarui);
    perbarui();
}

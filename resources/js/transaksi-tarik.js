/*
 * Form tarik (F4 §2 H.2.4, F4b C.5–C.7). Hanya tampilan: validasi saldo (BR-08) tetap di server.
 * Data: <script type="application/json" id="data-saldo"> berisi { id anggota aktif: saldo }. Anggota tanpa
 * transaksi Sukarela tidak ada di peta (dibaca 0). Peta kosong dikirim sebagai [] dan tetap aman.
 *
 * Konfirmasi (C.7): kiriman pertama dari form yang isiannya lengkap dicegat (preventDefault) dan modal
 * [data-konfirmasi-tarik] dibuka. Karena event sudah defaultPrevented, skrip global cegah-kirim-ganda tidak
 * menonaktifkan tombol. "Konfirmasi penarikan" memberi tanda lalu memanggil requestSubmit(); kiriman itu lolos dari
 * pencegat dan diproses cegah-kirim-ganda seperti biasa. Isian belum lengkap atau melebihi saldo: tanpa modal,
 * form langsung dikirim ke server dan pesan validasi tampil seperti biasa.
 */
const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n)); // setara Format::rupiah

export default function pasangSaldoTarik() {
    const elemenData = document.getElementById('data-saldo');
    const form = document.querySelector('form[data-form-transaksi="tarik"]');
    const anggota = form?.querySelector('input[type="hidden"][name="anggota_id"]');
    const jumlah = document.getElementById('jumlah');

    if (!elemenData || !form || !anggota || !jumlah) {
        return;
    }

    const saldoKosong = form.querySelector('[data-saldo-kosong]');
    const saldoAda = form.querySelector('[data-saldo-ada]');
    const nilaiSaldo = form.querySelector('[data-nilai-saldo]');
    const peringatan = form.querySelector('[data-peringatan-saldo]');
    const isiCepat = [...form.querySelectorAll('[data-isi-cepat]')];
    const modal = document.querySelector('[data-konfirmasi-tarik]');

    let data = {};
    try {
        data = JSON.parse(elemenData.textContent) || {};
    } catch {
        data = {};
    }

    const saldoTerpilih = () => (anggota.value === '' ? null : Number(data[anggota.value] ?? 0));

    // Angka jumlah setelah titik pemisah ribuan dihapus; bagian setelah koma diabaikan.
    const angkaJumlah = () => {
        const angka = jumlah.value.split(',')[0].replace(/\D/g, '');

        return angka === '' ? null : Number(angka);
    };

    // Desimal selain nol (mis. "1.500,50") ditolak server, jadi tidak dianggap lengkap.
    const desimalSah = () => {
        const bagian = jumlah.value.split(',');

        return bagian.length === 1 || /^0*$/.test(bagian.slice(1).join('').replace(/\D/g, ''));
    };

    function perbarui() {
        const saldo = saldoTerpilih();
        const diketik = angkaJumlah();

        if (saldoKosong && saldoAda && nilaiSaldo) {
            saldoKosong.hidden = saldo !== null;
            saldoAda.hidden = saldo === null;
            nilaiSaldo.textContent = saldo === null ? '' : rupiah(saldo);
        }

        if (peringatan) {
            peringatan.hidden = !(saldo !== null && diketik !== null && diketik > saldo);
        }

        // C.6: nominal di atas saldo redup dan tidak bisa diklik; "Semua saldo" mengisi seluruh saldo.
        isiCepat.forEach((el) => {
            if (el.dataset.isiCepat === 'semua') {
                el.dataset.nilai = saldo === null ? '' : String(Math.floor(saldo));
                el.disabled = saldo === null || saldo <= 0;
            } else {
                el.disabled = saldo !== null && Number(el.dataset.isiCepat) > saldo;
            }
        });
        document.dispatchEvent(new CustomEvent('isi-cepat:segarkan'));
    }

    document.addEventListener('anggota-dipilih', perbarui);
    jumlah.addEventListener('input', perbarui);
    perbarui();

    if (!modal) {
        return;
    }

    const nilaiBaris = (kunci) => modal.querySelector(`[data-konfirmasi-baris="${kunci}"] [data-konfirmasi-nilai]`);
    const tombolKirim = modal.querySelector('[data-konfirmasi-kirim]');
    let dikonfirmasi = false;

    form.addEventListener('submit', (event) => {
        if (dikonfirmasi) {
            dikonfirmasi = false; // kiriman akhir: biarkan lewat ke cegah-kirim-ganda dan server.

            return;
        }

        const saldo = saldoTerpilih();
        const diketik = angkaJumlah();

        if (saldo === null || diketik === null || diketik <= 0 || !desimalSah() || diketik > saldo) {
            return; // C.7: tanpa modal, form langsung dikirim seperti biasa.
        }

        event.preventDefault();

        const opsi = document.querySelector(`[data-opsi-anggota="${anggota.value}"]`);
        nilaiBaris('anggota').textContent = opsi ? `${opsi.dataset.nama} (${opsi.dataset.no})` : '';
        nilaiBaris('jumlah').textContent = rupiah(diketik);
        nilaiBaris('sisa').textContent = rupiah(saldo - diketik);

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'konfirmasi-tarik' }));
    });

    tombolKirim?.addEventListener('click', () => {
        dikonfirmasi = true;
        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'konfirmasi-tarik' }));
        form.requestSubmit();
    });
}

{{--
    Format titik ribuan pada input #jumlah saat mengetik (2000000 -> 2.000.000).
    Bagian setelah koma dibiarkan apa adanya supaya "1.500,50" tetap ditolak server.
    Normalisasi sebenarnya ada di server: App\Http\Requests\Concerns\NormalisasiJumlah.
--}}
<script>
    (function () {
        var input = document.getElementById('jumlah');

        if (! input) {
            return;
        }

        function format() {
            var bagian = input.value.split(',');
            var angka = bagian[0].replace(/\D/g, '');
            var hasil = angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            if (bagian.length > 1) {
                hasil += ',' + bagian.slice(1).join('').replace(/\D/g, '');
            }

            input.value = hasil;
        }

        input.addEventListener('input', format);
        format();
    })();
</script>

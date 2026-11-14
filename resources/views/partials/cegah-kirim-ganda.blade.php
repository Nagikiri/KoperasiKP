{{--
    Cegah kirim ganda (00 §4.7): setelah form benar-benar dikirim, tombol submit
    dinonaktifkan dan kiriman berikutnya dari form yang sama dibatalkan.
    - Berlaku untuk semua form di halaman (event submit ditangkap di document).
    - Form dengan onsubmit="return confirm(...)" yang dibatalkan tidak ikut
      dinonaktifkan, karena event-nya sudah defaultPrevented.
    - Saat halaman dipulihkan dari cache Back/Forward (pageshow persisted),
      tombol diaktifkan lagi, sehingga form GET (filter, laporan) tetap bisa
      dikirim ulang.
    Pengaman utama tetap di server: kode_referensi (BR-19).
--}}
<script data-cegah-kirim-ganda>
    (function () {
        function tombolSubmit(form) {
            return form.querySelectorAll('button:not([type]), button[type=submit], input[type=submit]');
        }

        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (event.defaultPrevented) {
                return;
            }

            if (form.getAttribute('data-terkirim') === '1') {
                event.preventDefault();
                return;
            }

            form.setAttribute('data-terkirim', '1');
            tombolSubmit(form).forEach(function (tombol) {
                tombol.disabled = true;
            });
        });

        window.addEventListener('pageshow', function (event) {
            if (! event.persisted) {
                return;
            }

            document.querySelectorAll('form[data-terkirim]').forEach(function (form) {
                form.removeAttribute('data-terkirim');
                tombolSubmit(form).forEach(function (tombol) {
                    tombol.disabled = false;
                });
            });
        });
    })();
</script>

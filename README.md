# Sistem Informasi KSP Karya Duta Utama

Aplikasi web untuk mendigitalkan pencatatan data anggota dan transaksi simpanan Koperasi Simpan Pinjam (KSP) Karya Duta Utama, yang sebelumnya dicatat manual di buku. Sistem dipakai oleh satu akun Staff/Admin koperasi. Pengunjung umum hanya dapat membuka halaman informasi publik tanpa login.

## Fitur

- Login dengan username dan password, logout, dan ganti password.
- Pembatasan akses: semua halaman admin wajib login.
- Manajemen anggota: tambah (nomor anggota otomatis `KSP-{TAHUN}-{URUT}`), cari, ubah, lihat detail, dan nonaktifkan (data tidak dihapus).
- Transaksi simpanan: setoran Simpanan Pokok, Wajib, dan Sukarela; penarikan Simpanan Sukarela dengan pengecekan saldo; catatan opsional per transaksi; pencegahan transaksi tercatat dobel.
- Saldo dihitung otomatis per anggota per jenis simpanan.
- Riwayat transaksi dengan filter anggota dan jenis simpanan.
- Dashboard ringkasan: anggota aktif/nonaktif/baru bulan ini, saldo per jenis, aktivitas hari ini dan bulan ini, tren 6 bulan, daftar anggota yang belum setor Simpanan Wajib, aksi cepat, dan transaksi terbaru.
- Rekap simpanan per jenis beserta grand total (bagian dari halaman Rekap & Laporan Keseluruhan).
- Laporan simpanan per anggota (satu atau semua anggota) dengan filter status dan rentang tanggal, pratinjau, serta ekspor PDF (A4 landscape) dan Excel.
- Laporan simpanan keseluruhan dengan filter periode, jenis simpanan, jenis transaksi, dan status anggota, beserta pratinjau dan ekspor PDF/Excel.
- Pencegahan data tercatat dobel saat form terkirim dua kali (tambah anggota dan transaksi).
- Halaman informasi publik.

## Stack

| Komponen | Versi |
|---|---|
| Laravel | 10.x |
| PHP | 8.1–8.3 |
| Laravel Breeze (Blade) | 1.29.x |
| barryvdh/laravel-dompdf | 3.1.x |
| maatwebsite/excel | 3.1.x |
| Database | MySQL 8 (test memakai SQLite in-memory) |

## Kebutuhan

- PHP 8.1–8.3 dengan ekstensi `gd`, `zip`, `dom`, `mbstring`, dan `pdo_mysql`
- MySQL 8
- Composer
- Node.js (beserta npm)

## Instalasi

```bash
git clone <url-repo> proyek-koperasi
cd proyek-koperasi
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Atur koneksi database di `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) dan buat database kosongnya di MySQL, lalu jalankan:

```bash
php artisan migrate --seed
php artisan serve
```

Aplikasi dapat dibuka di `http://127.0.0.1:8000`.

## Akun default

| Username | Password |
|---|---|
| `admin` | `password123` |

> **Peringatan:** password default ini **wajib diganti** segera setelah login pertama, lewat menu **Ganti Password**.

## Reset password manual

Sistem tidak menyediakan fitur lupa password. Jika password admin terlupa, developer mereset langsung lewat Tinker:

```bash
php artisan tinker
```

```php
App\Models\User::where('username', 'admin')->first()->update(['password' => 'passwordBaru123']);
```

Password otomatis disimpan dalam bentuk hash. Setelah itu login dengan password baru dan segera ganti lewat menu Ganti Password.

## Menjalankan test

Test memakai SQLite in-memory (diatur di `phpunit.xml`), sehingga tidak menyentuh database MySQL.

```bash
php artisan test
```

---



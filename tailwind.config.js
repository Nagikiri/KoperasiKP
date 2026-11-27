import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                arang: { DEFAULT: '#1F2328', 700: '#2D333B', 600: '#3D444D', 400: '#8B949E' },
                merah: { DEFAULT: '#C8102E', 700: '#A50D26', 100: '#F9D3D8', 50: '#FDECEE' },
                emas: { DEFAULT: '#D4A017', 700: '#9A7410', 50: '#FBF3DC' },
                // R1 D.1: palet data sesuai tema (grafik, titik, nominal setor/tarik). Teks memakai 700; titik, batang, latar memakai DEFAULT/50.
                setor: { DEFAULT: '#C8930E', 700: '#8A6508', 50: '#FBF3DC' }, // emas (setoran)
                tarik: { DEFAULT: '#57606A', 700: '#57606A', 50: '#EEF0F2' }, // abu arang (penarikan)
                // F2d A.2: HANYA untuk angka selisih bertanda (naik = saldo bertambah).
                naik: '#2F7D57',
                turun: '#B3412F',
                // F2c A.2: latar halaman admin (abu hangat).
                latar: '#EFEEEB',
            },
        },
    },

    plugins: [forms],
};

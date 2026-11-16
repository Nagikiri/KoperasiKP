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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                arang: { DEFAULT: '#1F2328', 700: '#2D333B', 600: '#3D444D', 400: '#8B949E' },
                merah: { DEFAULT: '#C8102E', 700: '#A50D26', 100: '#F9D3D8', 50: '#FDECEE' },
                emas: { DEFAULT: '#D4A017', 700: '#9A7410', 50: '#FBF3DC' },
                // F2c H: palet data final (grafik, garis aksen kartu, ikon, nominal +/-). Badge status tetap emerald/red.
                setor: { DEFAULT: '#4F6BED', 50: '#EEF1FE' }, // indigo (setoran)
                tarik: { DEFAULT: '#F0705A', 50: '#FEEFEC' }, // koral (penarikan)
                // F2c A.2: latar halaman admin (abu hangat).
                latar: '#EFEEEB',
            },
        },
    },

    plugins: [forms],
};

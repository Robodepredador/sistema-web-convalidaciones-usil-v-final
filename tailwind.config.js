import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                usil: {
                    presidencial: '#0B1E3F',
                    profundo: '#00195A',
                    navy: '#00205B',
                    corporativo: '#002363',
                    sombra: '#012085',
                    electrico: '#0036DC',
                    activo: '#0936E6',
                    cobalto: '#1D50DD',
                    champagne: '#C5A059',
                    doradoMate: '#E2B568',
                    dorado1: '#FFB81C',
                    dorado2: '#F3BE00',
                    antracita: '#333333',
                    marfil: '#FAFAFA',
                    fondo: '#F4F6F9',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                heading: ['Manrope', ...defaultTheme.fontFamily.sans],
                body: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [forms],
};

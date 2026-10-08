import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Sin modo oscuro automático: el sistema operativo del usuario no debe cambiar la marca.
    darkMode: 'class',

    theme: {
        extend: {
            // Línea de marca MERA (https://meracorporation.com/media-kit/)
            fontFamily: {
                sans: ['Montserrat', 'Arial', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                mera: {
                    green: '#024D25',      // MERA green (primario)
                    secondary: '#55882B',  // secondary green
                    accent: '#93C01F',     // green for accent
                    sky: '#81CFF4',        // light blue
                    aqua: '#8CBCC4',       // aquamarine blue
                },
            },
        },
    },

    plugins: [forms],
};

import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,tsx}',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            keyframes: {
                'slide-in': {
                    '0%': { transform: 'translateX(100%)', opacity: '0' },
                    '100%': { transform: 'translateX(0)', opacity: '1' },
                },
                'slide-out': {
                    '0%': { transform: 'translateX(0)', opacity: '1' },
                    '100%': { transform: 'translateX(100%)', opacity: '0' },
                },
            },
            animation: {
                'slide-in': 'slide-in 0.3s ease-out',
                'slide-out': 'slide-out 0.3s ease-in forwards',
            },
            colors: {
                primary: '#005288',
                'primary-container': '#003a63',
                'primary-fixed': '#d0e4ff',
                'primary-fixed-dim': '#9ccaff',
                'on-primary': '#ffffff',
                'on-primary-container': '#91c5ff',
                'on-primary-fixed': '#001d35',
                'on-primary-fixed-variant': '#00497b',

                secondary: '#006b5e',
                'secondary-container': '#94f0df',
                'secondary-fixed': '#97f3e2',
                'secondary-fixed-dim': '#7ad7c6',
                'on-secondary': '#ffffff',
                'on-secondary-container': '#006f62',
                'on-secondary-fixed': '#00201b',
                'on-secondary-fixed-variant': '#005047',

                tertiary: '#5a2c00',
                'tertiary-container': '#7c3f00',
                'tertiary-fixed': '#ffdcc4',
                'tertiary-fixed-dim': '#ffb781',
                'on-tertiary': '#ffffff',
                'on-tertiary-container': '#ffb073',
                'on-tertiary-fixed': '#2f1400',
                'on-tertiary-fixed-variant': '#6f3800',

                background: '#f7f9ff',
                surface: '#f7f9ff',
                'surface-bright': '#ffffff',
                'surface-dim': '#d7dae0',
                'surface-container-lowest': '#ffffff',
                'surface-container-low': '#f1f4fa',
                'surface-container': '#ebeef4',
                'surface-container-high': '#e5e8ee',
                'surface-container-highest': '#dfe3e8',
                'surface-variant': '#dfe3e8',

                'on-surface': '#181c20',
                'on-surface-variant': '#41474f',
                'inverse-on-surface': '#eef1f7',

                error: '#ba1a1a',
                'error-container': '#ffdad6',
                'on-error': '#ffffff',
                'on-error-container': '#93000a',

                outline: '#727780',
                'outline-variant': '#c1c7d1',
                'surface-tint': '#206298',
                'inverse-surface': '#2d3135',
                'inverse-primary': '#9ccaff',
            },

            fontFamily: {
                sans: ['Public Sans', ...defaultTheme.fontFamily.sans],
                headline: ['Public Sans', 'sans-serif'],
                body: ['Public Sans', 'sans-serif'],
                label: ['Public Sans', 'sans-serif'],
            },

            borderRadius: {
                DEFAULT: '0.125rem',
                lg: '0.25rem',
                xl: '0.5rem',
                full: '0.75rem',
            },
        },
    },

    plugins: [forms],
};

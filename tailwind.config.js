import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Tokens from design.md (Design System v1.0)
            colors: {
                primary: {
                    DEFAULT: '#2952E3',
                    dark: '#1B3BB8',
                    light: '#EAF0FF',
                },
                accent: {
                    green: '#3AB795',
                    orange: '#F5A623',
                    red: '#E8535A',
                    purple: '#8B7BE8',
                },
                surface: '#FFFFFF',
                canvas: '#F4F6FB',
                ink: {
                    DEFAULT: '#14161F',
                    muted: '#6E7480',
                    disabled: '#B4B8C2',
                },
                hairline: '#E7E9F0',
            },
            borderRadius: {
                card: '24px',
                'card-sm': '16px',
                btn: '16px',
                field: '12px',
            },
            boxShadow: {
                card: '0 4px 16px rgba(20, 22, 31, 0.06)',
                'card-elevated': '0 12px 32px rgba(41, 82, 227, 0.18)',
            },
        },
    },

    plugins: [forms],
};

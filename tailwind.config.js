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
                sans: ['Inter', 'system-ui', 'sans-serif', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: '#2563EB',
                primaryHover: '#1D4ED8',
                'primary-hover': '#1D4ED8', /* For test1 compatibility */
                primaryLight: '#EFF6FF',
                accent: '#F97316',
                accentHover: '#EA580C',
                surface: '#FFFFFF',
                background: '#F8FAFC',
                border: '#E2E8F0',
                borderHover: '#CBD5E1',
                textMain: '#0F172A',
                textMuted: '#475569',
                textLight: '#94A3B8',
                success: '#16A34A',
                successLight: '#DCFCE7',
                warning: '#EAB308',
                warningLight: '#FEF9C3',
                danger: '#DC2626',
                dangerLight: '#FEE2E2',
            },
            boxShadow: {
                'subtle': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                'card': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
                'elevated': '0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
                'focus': '0 0 0 3px rgba(37, 99, 235, 0.2)',
                'sidebar': '4px 0 24px -4px rgba(0, 0, 0, 0.08)',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'slide-up': 'slideUp 0.3s ease-out',
                'slide-in': 'slideIn 0.3s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(8px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                slideIn: {
                    '0%': { transform: 'translateX(-100%)' },
                    '100%': { transform: 'translateX(0)' },
                }
            }
        },
    },

    plugins: [forms],
};

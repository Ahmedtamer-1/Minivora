import forms from '@tailwindcss/forms';

const v = (name) => `var(--mv-${name})`;

/** Every color, radius and font comes from design tokens (resources/css/tokens.css). */
/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/js/**/*.tsx', './resources/views/**/*.blade.php'],
    darkMode: ['selector', '[data-theme="dark"]'],
    theme: {
        extend: {
            fontFamily: {
                sans: v('font-body'),
                display: v('font-sans'),
                mono: v('font-mono'),
            },
            fontSize: {
                // Figma "Minivora" text styles, in rem so Settings › Accessibility › Text size scales them.
                label: ['0.6875rem', { lineHeight: '0.8125rem', letterSpacing: '0.16em', fontWeight: '600' }],
                caption: ['0.8125rem', { lineHeight: '1.125rem', fontWeight: '500' }],
                greeting: ['2.125rem', { lineHeight: '2.5625rem', letterSpacing: '-0.03em', fontWeight: '300' }],
                numeral: ['3.25rem', { lineHeight: '3.25rem', letterSpacing: '-0.06em', fontWeight: '300' }],
                'numeral-sm': ['1.75rem', { lineHeight: '1.9375rem', letterSpacing: '-0.07em', fontWeight: '300' }],
            },
            colors: {
                canvas: v('bg-canvas'),
                surface: v('bg-surface'),
                elevated: v('bg-elevated'),
                'accent-subtle': v('bg-accent-subtle'),
                primary: {
                    DEFAULT: v('action-primary'),
                    hover: v('action-primary-hover'),
                    pressed: v('action-primary-pressed'),
                    fg: v('action-on-primary'),
                },
                secondary: {
                    DEFAULT: v('action-secondary'),
                    hover: v('action-secondary-hover'),
                    fg: v('action-on-secondary'),
                },
                disabled: { DEFAULT: v('action-disabled'), fg: v('action-on-disabled') },
                success: { DEFAULT: v('status-success'), subtle: v('status-success-subtle') },
                warning: { DEFAULT: v('status-warning'), subtle: v('status-warning-subtle') },
                danger: { DEFAULT: v('status-danger'), subtle: v('status-danger-subtle') },
                info: { DEFAULT: v('status-info'), subtle: v('status-info-subtle') },
                line: { DEFAULT: v('border-default'), strong: v('border-strong'), focus: v('border-focus'), control: v('border-control') },
                ink: { DEFAULT: v('text-primary'), muted: v('text-muted'), link: v('text-link'), accent: v('text-accent') },
                brand: { accent: v('brand-accent'), highlight: v('brand-highlight'), mark: v('brand-mark') },
                chart: { 1: v('chart-1'), 2: v('chart-2'), 3: v('chart-3'), 4: v('chart-4'), track: v('chart-track') },
                glass: { DEFAULT: v('glass-fill'), strong: v('glass-fill-strong'), stroke: v('glass-stroke') },
            },
            borderRadius: {
                sm: v('radius-sm'),
                md: v('radius-md'),
                lg: v('radius-lg'),
                xl: v('radius-xl'),
                '2xl': v('radius-2xl'),
            },
            boxShadow: {
                sm: v('shadow-sm'),
                md: v('shadow-md'),
                lg: v('shadow-lg'),
                glass: v('glass-shadow'),
                'glass-thick': v('glass-shadow-thick'),
            },
        },
    },
    plugins: [forms],
};

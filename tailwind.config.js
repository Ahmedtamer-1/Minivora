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
                line: { DEFAULT: v('border-default'), strong: v('border-strong'), focus: v('border-focus') },
                ink: { DEFAULT: v('text-primary'), muted: v('text-muted'), link: v('text-link') },
            },
            borderRadius: {
                sm: v('radius-sm'),
                md: v('radius-md'),
                lg: v('radius-lg'),
                xl: v('radius-xl'),
            },
            boxShadow: {
                sm: v('shadow-sm'),
                md: v('shadow-md'),
                lg: v('shadow-lg'),
                glass: v('glass-shadow'),
            },
        },
    },
    plugins: [forms],
};

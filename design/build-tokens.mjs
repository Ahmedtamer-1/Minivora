// Reads design/tokens/tokens.json and writes resources/css/tokens.css (--mv-* variables, Light + Dark).
// Run with: npm run tokens
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const tokens = JSON.parse(readFileSync(resolve(root, 'design/tokens/tokens.json'), 'utf8'));

const name = (key) => key.replace(/\//g, '-');
const block = (entries, indent = '    ') => entries.map(([k, v]) => `${indent}--mv-${k}: ${v};`).join('\n');

const shared = [
    ...Object.entries(tokens.font).map(([k, v]) => [`font-${k}`, v]),
    ...Object.entries(tokens.radius).map(([k, v]) => [`radius-${k}`, v]),
    ...Object.entries(tokens.space).map(([k, v]) => [`space-${k}`, v]),
    ...Object.entries(tokens.shadow).map(([k, v]) => [`shadow-${k}`, v]),
];
const light = Object.entries(tokens.color.light).map(([k, v]) => [name(k), v]);
const dark = Object.entries(tokens.color.dark).map(([k, v]) => [name(k), v]);

const css = `/* GENERATED from design/tokens/tokens.json by \`npm run tokens\`. Do not edit by hand.
   Holds --mv-* CSS variables for Light and Dark ([data-theme="dark"], or the OS setting). */

:root {
${block(shared)}
${block(light)}
    color-scheme: light;
}

[data-theme='dark'] {
${block(dark)}
    color-scheme: dark;
}

@media (prefers-color-scheme: dark) {
    :root:not([data-theme='light']) {
${block(dark, '        ')}
        color-scheme: dark;
    }
}
`;

writeFileSync(resolve(root, 'resources/css/tokens.css'), css);
console.log(`tokens.css written: ${light.length} colors per mode, ${shared.length} shared tokens.`);

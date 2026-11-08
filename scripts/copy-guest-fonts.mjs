// Copies the theme fonts listed in resources/fonts/guest-fonts.json from
// node_modules into public/fonts/guest/{key}/, so guest pages serve them
// from our own domain (no third-party font requests).
import { copyFileSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const fonts = JSON.parse(readFileSync(join(root, 'resources/fonts/guest-fonts.json'), 'utf8'));
let copied = 0;

for (const [key, font] of Object.entries(fonts)) {
    const target = join(root, 'public/fonts/guest', key);
    mkdirSync(target, { recursive: true });

    for (const face of font.faces) {
        copyFileSync(join(root, 'node_modules', font.package, 'files', face.file), join(target, face.file));
        copied++;
    }
}

console.log(`Theme fonts: ${copied} files in public/fonts/guest`);

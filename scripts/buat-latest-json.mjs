/**
 * Membuat `latest.json` untuk tauri-plugin-updater dari hasil `npx tauri build`.
 *
 * Pakai:
 *   node scripts/buat-latest-json.mjs
 *
 * Hasil: `latest.json` di root proyek (unggah bersama
 * `KARSA_<versi>_x64-setup.nsis.zip` + `.sig` ke GitHub Release bertag v<versi>).
 */
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const conf = JSON.parse(readFileSync(join(root, 'src-tauri/tauri.conf.json'), 'utf8'));
const versi = conf.version;
const repo = 'Mukhamadirfan1997/RKAS-APP';

const dirNsis = join(root, 'src-tauri/target/release/bundle/nsis');
if (!existsSync(dirNsis)) {
    console.error('Folder bundle NSIS tidak ada. Jalankan dulu: npx tauri build');
    process.exit(1);
}

const nama = `KARSA_${versi}_x64-setup.nsis.zip`;
const berkas = readdirSync(dirNsis);
if (!berkas.includes(nama)) {
    console.error(`Berkas updater "${nama}" tidak ditemukan di ${dirNsis}.`);
    console.error('Pastikan tauri.conf.json memakai "createUpdaterArtifacts": true.');
    process.exit(1);
}

const sigPath = join(dirNsis, `${nama}.sig`);
if (!existsSync(sigPath)) {
    console.error(`Tanda tangan "${nama}.sig" tidak ditemukan. Build tanpa kunci signing?`);
    process.exit(1);
}

const signature = readFileSync(sigPath, 'utf8').trim();
const url = `https://github.com/${repo}/releases/download/v${versi}/${nama}`;

const data = {
    version: versi,
    notes: `KARSA v${versi} — pembaruan otomatis (pasang & buka ulang sendiri) dan perbaikan dialog simpan berkas.`,
    pub_date: new Date().toISOString(),
    platforms: {
        'windows-x86_64': { signature, url },
    },
};

const tujuan = join(root, 'latest.json');
writeFileSync(tujuan, JSON.stringify(data, null, 2) + '\n');
console.log(`latest.json dibuat (versi ${versi}).`);
console.log(`URL paket: ${url}`);

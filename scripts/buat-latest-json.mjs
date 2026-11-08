/**
 * Membuat `latest.json` untuk tauri-plugin-updater dari hasil `npx tauri build`.
 *
 * Pakai:
 *   node scripts/buat-latest-json.mjs      (atau: npm run rilis:manifest)
 *
 * Tauri v2 dengan `"createUpdaterArtifacts": true` menghasilkan paket updater
 * berupa installer NSIS itu sendiri (`KARSA_<versi>_x64-setup.exe`) beserta
 * tanda tangannya (`.exe.sig`). Versi Tauri lama memakai
 * `KARSA_<versi>_x64-setup.nsis.zip` + `.sig`; keduanya didukung di sini.
 *
 * Hasil: `latest.json` di root proyek (unggah bersama paket updater + `.sig`
 * ke GitHub Release bertag v<versi>).
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

const berkas = readdirSync(dirNsis);

/** Urutan prioritas: paket updater Tauri v2 (.exe) lalu v1 (.nsis.zip). */
const kandidat = [
    `KARSA_${versi}_x64-setup.exe`,
    `KARSA_${versi}_x64-setup.nsis.zip`,
];

const nama = kandidat.find((k) => berkas.includes(k));

if (!nama) {
    console.error(`Paket updater untuk v${versi} tidak ditemukan di ${dirNsis}.`);
    console.error('Pastikan tauri.conf.json memakai "createUpdaterArtifacts": true dan build memakai kunci signing.');
    process.exit(1);
}

const sigPath = join(dirNsis, `${nama}.sig`);
if (!existsSync(sigPath)) {
    console.error(`Tanda tangan "${nama}.sig" tidak ditemukan. Build tanpa kunci signing?`);
    process.exit(1);
}

const signature = readFileSync(sigPath, 'utf8').trim();
const url = `https://github.com/${repo}/releases/download/v${versi}/${nama}`;

// Catatan rilis untuk dialog pembaruan: pakai `--catatan "..."` bila diberikan,
// kalau tidak pakai teks ringkas yang tidak mengklaim fitur tertentu (dulu teks
// di sini di-hardcode dan jadi menyesatkan saat dipakai versi berikutnya).
const argCatatan = process.argv.indexOf('--catatan');
const catatan = argCatatan > -1 && process.argv[argCatatan + 1]
    ? String(process.argv[argCatatan + 1]).trim()
    : `KARSA v${versi} - pembaruan aplikasi. Rincian perubahan lihat catatan rilis di GitHub.`;

const data = {
    version: versi,
    notes: catatan,
    pub_date: new Date().toISOString(),
    platforms: {
        'windows-x86_64': { signature, url },
    },
};

const tujuan = join(root, 'latest.json');
writeFileSync(tujuan, JSON.stringify(data, null, 2) + '\n');
console.log(`latest.json dibuat (versi ${versi}).`);
console.log(`Paket updater : ${nama}`);
console.log(`URL paket     : ${url}`);

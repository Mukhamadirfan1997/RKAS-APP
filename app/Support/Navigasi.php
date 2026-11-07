<?php

namespace App\Support;

/**
 * Daftar navigasi aplikasi (dipakai sidebar & menu dropdown topbar).
 * Satu sumber data agar dua tampilan tidak pernah berbeda isi.
 */
class Navigasi
{
    /**
     * Menu utama ringkas khusus sidebar (hanya Dashboard dan Lembar Kerja yang sering diakses).
     * Seluruh menu lainnya tetap tersedia lengkap di menu dropdown akun pada topbar.
     *
     * @return array<int, array{route:string,label:string,svg:string,group:string}>
     */
    public static function sidebar(): array
    {
        return [
            [
                'route' => 'dashboard.index',
                'label' => 'Dashboard',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h14V10"/>',
                'group' => 'dashboard',
            ],
            [
                'route' => 'rkas.index',
                'label' => 'Lembar Kerja',
                'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                'group' => 'rkas',
            ],
        ];
    }

    /**
     * @return array<string, array<int, array{route:string,label:string,svg:string,group:string}>>
     */
    public static function grup(): array
    {
        return [
            'Kerja Harian' => [
                [
                    'route' => 'dashboard.index',
                    'label' => 'Dashboard',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h14V10"/>',
                    'group' => 'dashboard',
                ],
                [
                    'route' => 'rkas.index',
                    'label' => 'Lembar Kerja',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                    'group' => 'rkas',
                ],
                [
                    'route' => 'monitoring.juknis',
                    'label' => 'Monitoring JUKNIS',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                    'group' => 'monitoring',
                ],
            ],
            'Pengaturan & Kelola' => [
                [
                    'route' => 'master.program',
                    'label' => 'Master Data',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>',
                    'group' => 'master',
                ],
                [
                    'route' => 'audit.index',
                    'label' => 'Audit Log',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    'group' => 'audit',
                ],
                [
                    'route' => 'backup.index',
                    'label' => 'Backup',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>',
                    'group' => 'backup',
                ],
                [
                    'route' => 'pengaturan.profil',
                    'label' => 'Pengaturan',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    'group' => 'pengaturan',
                ],
                [
                    'route' => 'pengaturan.katalog',
                    'label' => 'Update Katalog',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6h.1a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>',
                    'group' => 'pengaturan.katalog',
                ],
                [
                    'route' => 'aktivasi.index',
                    'label' => 'Aktivasi Lisensi',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>',
                    'group' => 'aktivasi',
                ],
            ],
            'Bantuan' => [
                [
                    'route' => 'tentang.index',
                    'label' => 'Tentang Aplikasi',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    'group' => 'tentang',
                ],
                [
                    'route' => 'panduan.index',
                    'label' => 'Panduan Penggunaan',
                    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                    'group' => 'panduan',
                ],
            ],
        ];
    }

    /**
     * Apakah grup navigasi sedang aktif untuk nama route saat ini.
     */
    public static function aktif(string $group, ?string $current): bool
    {
        $current = (string) $current;

        if ($group === 'pengaturan') {
            return str_starts_with($current, 'pengaturan.') && ! str_starts_with($current, 'pengaturan.katalog');
        }

        if ($group === 'pengaturan.katalog') {
            return $current === 'pengaturan.katalog' || str_starts_with($current, 'pengaturan.katalog.');
        }

        return $current === $group.'.index' || str_starts_with($current, $group.'.');
    }

    /**
     * Label halaman aktif (dipakai topbar sebagai judul konteks).
     */
    public static function labelAktif(?string $current): ?string
    {
        foreach (self::grup() as $items) {
            foreach ($items as $item) {
                if (self::aktif($item['group'], $current)) {
                    return $item['label'];
                }
            }
        }

        return null;
    }
}

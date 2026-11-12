<?php

namespace Tests\Support;

use App\Services\JaringanService;
use App\Services\LanConfigService;

/**
 * JaringanService dengan daftar antarmuka buatan.
 *
 * Dipakai pengujian supaya hasilnya tidak bergantung pada kartu jaringan
 * komputer yang menjalankan test. Logika penyaringan adapter tetap memakai
 * `adapterDitolak()` milik induk, sehingga aturan virtual/VPN ikut teruji.
 */
class JaringanUji extends JaringanService
{
    /**
     * @param  list<array{nama?:string,ip:string,netmask?:string,up?:bool,virtual?:bool}>  $daftar
     */
    public function __construct(private array $daftar, LanConfigService $lan)
    {
        parent::__construct($lan);
    }

    public function antarmuka(): array
    {
        $hasil = [];

        foreach ($this->daftar as $i => $a) {
            $nama = (string) ($a['nama'] ?? 'Adapter '.$i);
            $ip = (string) ($a['ip'] ?? '');
            $netmask = (string) ($a['netmask'] ?? '255.255.255.0');

            $hasil[] = [
                'nama' => $nama,
                'guid' => 'uji-'.$i,
                'ip' => $ip,
                'netmask' => $netmask,
                'cidr' => JaringanService::cidr($ip, $netmask),
                'up' => (bool) ($a['up'] ?? true),
                'virtual' => (bool) ($a['virtual'] ?? $this->adapterDitolak(strtolower($nama))),
                'lokal' => $this->ipLokal($ip),
                'link_lokal' => $this->ipLinkLokal($ip),
            ];
        }

        return $hasil;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deteksi jaringan lokal komputer ini — SATU sumber kebenaran untuk:
 * - alamat IP LAN yang sedang aktif (ikut berubah otomatis saat DHCP),
 * - subnet yang boleh mengakses KARSA,
 * - lokasi "lokal" (localhost) yang selalu diizinkan,
 * - jumlah komputer lain yang sedang terhubung.
 *
 * Catatan penting: subnet dihitung ULANG setiap permintaan (tidak disimpan),
 * sehingga perubahan IP akibat DHCP langsung terdeteksi tanpa restart server.
 */
class JaringanService
{
    /** Jumlah menit sebuah sesi dianggap masih "terhubung". */
    public const MENIT_KLIEN_AKTIF = 5;

    /**
     * Kata kunci deskripsi adapter yang SELALU dianggap bukan jaringan fisik:
     * adapter virtual, VPN, tunnel, atau loopback. Klien yang datang lewat
     * adapter ini tidak akan pernah dianggap "jaringan lokal yang diizinkan".
     *
     * @var list<string>
     */
    public const ADAPTER_DITOLAK = [
        'loopback',
        'wi-fi direct',
        'wifi direct',
        'virtual',
        'vmware',
        'virtualbox',
        'hyper-v',
        'vethernet',
        'docker',
        'wsl',
        'tap-',
        'tap adapter',
        'tap-windows',
        'tunnel',
        'vpn',
        'tailscale',
        'zerotier',
        'wireguard',
        'openvpn',
        'bluetooth',
        'teredo',
        'isatap',
        'npcap',
        'hamachi',
        'radmin',
        'sangfor',
        'fortinet',
        'anyconnect',
        'sonicwall',
        'globalprotect',
        'pulse secure',
        'nordlynx',
        'proton',
        'mullvad',
        'softether',
        'pptp',
        'l2tp',
    ];

    /** @var list<array{nama:string,guid:string,ip:string,netmask:string,cidr:?string,up:bool,virtual:bool,lokal:bool,link_lokal:bool}>|null */
    private ?array $cacheAntarmuka = null;

    public function __construct(private LanConfigService $lan) {}

    // ------------------------------------------------------------------
    // Deteksi antarmuka
    // ------------------------------------------------------------------

    /**
     * Semua antarmuka IPv4 beserta statusnya (termasuk yang virtual & nonaktif).
     *
     * @return list<array{nama:string,guid:string,ip:string,netmask:string,cidr:?string,up:bool,virtual:bool,lokal:bool,link_lokal:bool}>
     */
    public function antarmuka(): array
    {
        if ($this->cacheAntarmuka !== null) {
            return $this->cacheAntarmuka;
        }

        $hasil = [];
        $mentah = @net_get_interfaces();
        if (! is_array($mentah)) {
            return $this->cacheAntarmuka = [];
        }

        foreach ($mentah as $guid => $info) {
            $deskripsi = trim((string) ($info['description'] ?? ''));
            if ($deskripsi === '') {
                $deskripsi = (string) $guid;
            }
            $nama = strtolower($deskripsi);
            $up = (bool) ($info['up'] ?? false);
            $virtual = $this->adapterDitolak($nama);

            foreach ((array) ($info['unicast'] ?? []) as $unicast) {
                // 2 = AF_INET (IPv4) — nilai sama di Windows maupun Linux.
                if ((int) ($unicast['family'] ?? 0) !== 2) {
                    continue;
                }
                $ip = (string) ($unicast['address'] ?? '');
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    continue;
                }
                $netmask = (string) ($unicast['netmask'] ?? '255.255.255.0');
                $lokal = $this->ipLokal($ip);

                $hasil[] = [
                    'nama' => $deskripsi,
                    'guid' => (string) $guid,
                    'ip' => $ip,
                    'netmask' => $netmask,
                    'cidr' => self::cidr($ip, $netmask),
                    'up' => $up,
                    'virtual' => $virtual,
                    'lokal' => $lokal,
                    'link_lokal' => $this->ipLinkLokal($ip),
                ];
            }
        }

        return $this->cacheAntarmuka = $hasil;
    }

    /**
     * Antarmuka "nyata": aktif, bukan virtual/VPN, bukan loopback, bukan APIPA.
     *
     * @return list<array{nama:string,guid:string,ip:string,netmask:string,cidr:?string,up:bool,virtual:bool,lokal:bool,link_lokal:bool}>
     */
    public function antarmukaFisik(): array
    {
        return array_values(array_filter($this->antarmuka(), function (array $a): bool {
            return $a['up']
                && ! $a['virtual']
                && ! $a['lokal']
                && ! $a['link_lokal']
                && $a['cidr'] !== null;
        }));
    }

    /**
     * Alamat IP LAN utama (yang dipakai untuk URL "buka dari komputer lain").
     */
    public function ipUtama(): ?string
    {
        $fisik = $this->antarmukaFisik();
        if ($fisik === []) {
            return null;
        }

        $kandidat = array_column($fisik, 'ip');

        // Prioritaskan alamat yang didaftarkan Windows untuk nama komputer ini;
        // biasanya itulah kartu jaringan yang benar-benar dipakai.
        $terdaftar = @gethostbynamel((string) gethostname());
        foreach (is_array($terdaftar) ? $terdaftar : [] as $ip) {
            if (in_array($ip, $kandidat, true)) {
                return $ip;
            }
        }

        return $kandidat[0];
    }

    /**
     * Daftar subnet (CIDR) yang boleh mengakses KARSA.
     *
     * Hanya subnet dari adapter FISIK yang aktif. Ini yang membuat klien dari
     * jaringan VPN / adapter virtual ditolak, meskipun alamatnya juga RFC1918.
     *
     * @return list<string>
     */
    public function subnetDiizinkan(): array
    {
        return array_values(array_unique(array_filter(
            array_column($this->antarmukaFisik(), 'cidr')
        )));
    }

    // ------------------------------------------------------------------
    // Pemeriksaan alamat
    // ------------------------------------------------------------------

    public function normalisasiIp(?string $ip): string
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return '';
        }
        // Bentuk IPv4-mapped IPv6 (::ffff:192.168.1.20)
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        // Buang zone id (mis. fe80::1%12)
        $posisi = strpos($ip, '%');
        if ($posisi !== false) {
            $ip = substr($ip, 0, $posisi);
        }

        return $ip;
    }

    /** Apakah alamat ini komputer KARSA sendiri (localhost)? */
    public function ipLokal(?string $ip): bool
    {
        $ip = $this->normalisasiIp($ip);
        if ($ip === '') {
            return false;
        }

        return $ip === '127.0.0.1'
            || $ip === '::1'
            || str_starts_with($ip, '127.')
            || $ip === '0.0.0.0';
    }

    public function ipLinkLokal(string $ip): bool
    {
        return str_starts_with($ip, '169.254.');
    }

    /**
     * Apakah klien boleh dihubungkan? Hanya bila alamatnya termasuk subnet
     * salah satu adapter fisik aktif. Alamat RFC1918 milik VPN TIDAK lolos.
     */
    public function ipDiizinkan(?string $ip): bool
    {
        $ip = $this->normalisasiIp($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        foreach ($this->subnetDiizinkan() as $cidr) {
            if (self::ipDiCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Status & ringkasan untuk antarmuka
    // ------------------------------------------------------------------

    /**
     * Status jaringan untuk ditampilkan:
     * - `nonaktif`  : LAN dimatikan operator (kondisi normal desktop offline)
     * - `terputus`  : LAN diminta aktif, tetapi tidak ada adapter fisik terhubung
     * - `aktif`     : LAN aktif dan siap diakses komputer lain
     */
    public function status(): string
    {
        if (! $this->lan->aktif()) {
            return 'nonaktif';
        }

        return $this->antarmukaFisik() === [] ? 'terputus' : 'aktif';
    }

    public function alamatLokal(?int $port): ?string
    {
        return $port ? 'http://127.0.0.1:'.$port : null;
    }

    public function alamatLan(?int $port): ?string
    {
        $ip = $this->ipUtama();

        return ($ip !== null && $port) ? 'http://'.$ip.':'.$port : null;
    }

    /**
     * Jumlah komputer LAIN yang sesinya masih aktif (bukan localhost).
     * Dipakai sebagai indikator "sedang terhubung N komputer".
     */
    public function jumlahKlienAktif(int $menit = self::MENIT_KLIEN_AKTIF): int
    {
        try {
            $batas = now()->subMinutes(max(1, $menit))->getTimestamp();

            return (int) DB::table('sessions')
                ->where('last_activity', '>=', $batas)
                ->whereNotNull('ip_address')
                ->where('ip_address', 'not like', '127.%')
                ->where('ip_address', '!=', '::1')
                ->where('ip_address', '!=', '')
                ->distinct()
                ->count('ip_address');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Data siap pakai untuk halaman Tentang / tombol pengaturan LAN.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(?int $port): array
    {
        return [
            'aktif' => $this->lan->aktif(),
            'status' => $this->status(),
            'ip_utama' => $this->ipUtama(),
            'alamat_lokal' => $this->alamatLokal($port),
            'alamat_lan' => $this->alamatLan($port),
            'subnet' => $this->subnetDiizinkan(),
            'klien_aktif' => $this->jumlahKlienAktif(),
            'antarmuka' => array_map(
                fn (array $a): array => ['nama' => $a['nama'], 'ip' => $a['ip'], 'cidr' => $a['cidr']],
                $this->antarmukaFisik()
            ),
        ];
    }

    // ------------------------------------------------------------------
    // Helper CIDR
    // ------------------------------------------------------------------

    /**
     * Bentuk CIDR dari alamat + netmask, memakai ALAMAT JARINGAN
     * (mis. 192.168.1.17 + 255.255.255.0 -> "192.168.1.0/24").
     * Dipakai untuk tampilan "jaringan yang diizinkan" agar tidak
     * menampilkan alamat komputer seolah-olah itu nama jaringan.
     */
    public static function cidr(string $ip, string $netmask): ?string
    {
        $prefix = self::prefixDariNetmask($netmask);
        if ($prefix === null) {
            return null;
        }

        $alamat = ip2long($ip);
        if ($alamat === false) {
            return null;
        }

        $mask = $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;

        return long2ip($alamat & $mask).'/'.$prefix;
    }

    public static function prefixDariNetmask(string $netmask): ?int
    {
        $biner = '';
        foreach (explode('.', $netmask) as $oktet) {
            if (! is_numeric($oktet)) {
                return null;
            }
            $biner .= str_pad(decbin((int) $oktet & 0xFF), 8, '0', STR_PAD_LEFT);
        }

        // Netmask wajib berurutan: 1...10...0
        if (preg_match('/^1*0*$/', $biner) !== 1) {
            return null;
        }

        return substr_count($biner, '1');
    }

    public static function ipDiCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $prefix] = explode('/', $cidr, 2);
        if (! is_numeric($prefix)) {
            return false;
        }
        $prefix = (int) $prefix;
        if ($prefix < 0 || $prefix > 32) {
            return false;
        }

        $alamat = ip2long($ip);
        $jaringan = ip2long($subnet);
        if ($alamat === false || $jaringan === false) {
            return false;
        }

        $mask = $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;

        return ($alamat & $mask) === ($jaringan & $mask);
    }

    protected function adapterDitolak(string $namaAdapter): bool
    {
        $daftar = (array) config('karsa.lan.adapter_ditolak', self::ADAPTER_DITOLAK);

        foreach ($daftar as $kata) {
            $kata = strtolower(trim((string) $kata));
            if ($kata !== '' && str_contains($namaAdapter, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Catat penolakan akses ke log maksimal sekali per menit per IP agar log
     * tidak membanjir bila ada percobaan berulang.
     */
    public function catatPenolakan(string $ip, string $alasan, string $path): void
    {
        $kunci = 'karsa.lan.tolak.'.md5($ip.'|'.$alasan);

        try {
            if (Cache::add($kunci, true, 60)) {
                Log::warning('Akses jaringan ditolak', [
                    'ip' => $ip,
                    'alasan' => $alasan,
                    'path' => $path,
                ]);
            }
        } catch (\Throwable $e) {
            // Log tidak boleh mengganggu permintaan.
        }
    }
}

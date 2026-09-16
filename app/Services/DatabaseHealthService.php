<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseHealthService
{
    /** @var string|null Fake untuk test: jika set, dipakai sebagai hasil integrity_check */
    public static ?string $fakeIntegrityResult = null;

    /**
     * Cek apakah pengecekan integritas perlu dijalankan.
     * Maksimal 1x per 30 hari — mirip pola ensureDailyAutoBackup.
     */
    public static function shouldRunCheck(): bool
    {
        try {
            $row = PengaturanSekolah::first();
            if (! $row) {
                return true;
            }
            $last = $row->db_last_integrity_check_at;
            if ($last === null) {
                return true;
            }
            $lastCarbon = $last instanceof Carbon ? $last : Carbon::parse($last);

            return $lastCarbon->diffInDays(now()) >= 30;
        } catch (\Throwable $e) {
            // Jangan blokir boot jika kolom belum migrasi / error
            Log::warning('DatabaseHealth shouldRunCheck gagal: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Jalankan PRAGMA integrity_check.
     * Return: ['ok' => bool, 'message' => string]
     */
    public static function runIntegrityCheck(): array
    {
        try {
            if (static::$fakeIntegrityResult !== null) {
                $rows = [(object) ['integrity_check' => static::$fakeIntegrityResult]];
            } else {
                $rows = DB::select('PRAGMA integrity_check');
            }
            $message = null;
            if (empty($rows)) {
                $message = 'Empty integrity_check result';
            } else {
                $first = $rows[0];
                // PDO bisa return object stdClass atau associative
                if (is_object($first)) {
                    $vals = array_values((array) $first);
                    $message = $vals[0] ?? null;
                } elseif (is_array($first)) {
                    $message = array_values($first)[0] ?? null;
                } else {
                    $message = (string) $first;
                }
                // Jika ada multiple rows, gabung (error multi-baris)
                if (count($rows) > 1) {
                    $all = [];
                    foreach ($rows as $r) {
                        $v = is_object($r) ? array_values((array) $r)[0] ?? '' : (is_array($r) ? array_values($r)[0] ?? '' : (string) $r);
                        $all[] = trim((string) $v);
                    }
                    $message = implode('; ', $all);
                }
            }

            $isOk = is_string($message) && strtolower(trim($message)) === 'ok';

            $now = now();
            // Guard fresh install: pengaturan_sekolah bisa kosong (belum pernah simpan profil)
            // — jangan throw, buat baris default bila perlu agar timestamp/flag tetap persist
            $pengaturan = null;
            try {
                $pengaturan = PengaturanSekolah::first();
                if (! $pengaturan) {
                    // Buat tanpa trigger LogsActivity
                    try {
                        $pengaturan = PengaturanSekolah::withoutEvents(fn () => PengaturanSekolah::create([]));
                    } catch (\Throwable $e) {
                        $id = DB::table('pengaturan_sekolah')->insertGetId([
                            'nama_sekolah' => 'SD NEGERI TOYANING 1',
                            'status_sekolah' => 'negeri',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $pengaturan = PengaturanSekolah::find($id);
                    }
                    if (! $pengaturan) {
                        $pengaturan = PengaturanSekolah::first();
                    }
                }
            } catch (\Throwable $e) {
                // Fallback aman: jangan gagalkan integrity_check hanya karena create gagal
                // (mis. mass-assignment atau DB belum migrasi)
                try {
                    $pengaturan = PengaturanSekolah::first();
                } catch (\Throwable $e2) {
                    $pengaturan = null;
                }
                if (! $pengaturan) {
                    Log::warning('DatabaseHealth auto-create pengaturan_sekolah gagal: '.$e->getMessage());
                }
            }

            if ($isOk) {
                if ($pengaturan) {
                    try {
                        $payload = [
                            'db_last_integrity_check_at' => $now,
                            'db_corrupt_detected_at' => null,
                            'db_corrupt_message' => null,
                        ];
                        if (method_exists($pengaturan, 'saveQuietly')) {
                            $pengaturan->forceFill($payload)->saveQuietly();
                        } else {
                            DB::table('pengaturan_sekolah')->where('id', $pengaturan->id)->update(array_merge($payload, ['updated_at' => $now]));
                        }
                    } catch (\Throwable $e) {
                        Log::warning('DatabaseHealth simpan sehat gagal: '.$e->getMessage());
                    }
                }

                return ['ok' => true, 'message' => 'ok'];
            }

            // TIDAK sehat
            $rawMessage = $message ?? 'unknown integrity error';
            if ($pengaturan) {
                try {
                    $payload = [
                        'db_last_integrity_check_at' => $now,
                        'db_corrupt_detected_at' => $now,
                        'db_corrupt_message' => $rawMessage,
                    ];
                    if (method_exists($pengaturan, 'saveQuietly')) {
                        $pengaturan->forceFill($payload)->saveQuietly();
                    } else {
                        DB::table('pengaturan_sekolah')->where('id', $pengaturan->id)->update(array_merge($payload, ['updated_at' => $now]));
                    }
                } catch (\Throwable $e) {
                    Log::warning('DatabaseHealth simpan korup gagal: '.$e->getMessage());
                }
            }

            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'database.corrupt_detected',
                    'auditable_type' => 'Database',
                    'auditable_id' => null,
                    'description' => 'PRAGMA integrity_check gagal: '.$rawMessage,
                    'old_values' => null,
                    'new_values' => ['message' => $rawMessage],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Audit corrupt_detected gagal: '.$e->getMessage());
            }

            return ['ok' => false, 'message' => $rawMessage];
        } catch (\Throwable $e) {
            Log::warning('runIntegrityCheck exception: '.$e->getMessage());

            // Jangan lempar — kembalikan gagal ringan supaya boot tetap jalan
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function isCorrupt(): bool
    {
        try {
            $row = PengaturanSekolah::first();
            if (! $row) {
                return false;
            }

            return $row->db_corrupt_detected_at !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getCorruptMessage(): ?string
    {
        try {
            $row = PengaturanSekolah::first();

            return $row?->db_corrupt_message;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Aktifkan WAL + synchronous NORMAL secara idempoten.
     */
    public static function enableWal(): void
    {
        try {
            // Hanya untuk sqlite file (bukan :memory:)
            $dbName = DB::connection()->getDatabaseName();
            if ($dbName === ':memory:' || $dbName === '' || $dbName === null) {
                return;
            }
            DB::statement('PRAGMA journal_mode=WAL');
            DB::statement('PRAGMA synchronous=NORMAL');
            // Verifikasi opsional — log jika tidak wal
            try {
                $r = DB::select('PRAGMA journal_mode');
                $mode = null;
                if (! empty($r)) {
                    $first = $r[0];
                    $vals = is_object($first) ? array_values((array) $first) : (is_array($first) ? array_values($first) : [(string) $first]);
                    $mode = strtolower(trim((string) ($vals[0] ?? '')));
                }
                if ($mode !== 'wal') {
                    Log::warning('PRAGMA journal_mode bukan wal setelah enableWal: '.($mode ?? 'null'));
                }
            } catch (\Throwable $e) {
            }
        } catch (\Throwable $e) {
            Log::warning('enableWal gagal: '.$e->getMessage());
        }
    }

    /**
     * Helper untuk testing: reset flag korup.
     */
    public static function clearCorruptFlag(): void
    {
        $row = PengaturanSekolah::first();
        if ($row) {
            $row->forceFill([
                'db_corrupt_detected_at' => null,
                'db_corrupt_message' => null,
            ])->save();
        }
    }
}

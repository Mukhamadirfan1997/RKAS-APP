<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\PengaturanSekolah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLogArchiveService
{
    public const RETENTION_DAYS = 90;

    public const ARCHIVE_INTERVAL_DAYS = 30;

    public static function shouldRunArchive(): bool
    {
        try {
            $row = PengaturanSekolah::first();
            if (! $row) {
                return true;
            }
            $last = $row->audit_last_archive_at ?? null;
            if ($last === null) {
                return true;
            }
            $lastCarbon = $last instanceof Carbon ? $last : Carbon::parse($last);

            return $lastCarbon->diffInDays(now()) >= self::ARCHIVE_INTERVAL_DAYS;
        } catch (\Throwable $e) {
            Log::warning('AuditLogArchive shouldRunArchive gagal: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Arsipkan audit_logs yang lebih tua dari RETENTION_DAYS ke audit_log_archives.
     * Return ['archived'=>int,'remaining'=>int]
     */
    public static function runArchive(): array
    {
        $now = now();
        $cutoff = $now->copy()->subDays(self::RETENTION_DAYS);

        $archivedCount = 0;

        try {
            $pengaturan = null;
            try {
                $pengaturan = PengaturanSekolah::first();
                if (! $pengaturan) {
                    // Buat tanpa trigger LogsActivity (quiet)
                    if (class_exists(Model::class) && method_exists(PengaturanSekolah::class, 'createQuietly')) {
                        // Laravel 11+ createQuietly tidak ada, pakai withoutEvents
                        $pengaturan = PengaturanSekolah::withoutEvents(fn () => PengaturanSekolah::create([]));
                    } else {
                        // Fallback: insert langsung via query builder (tidak trigger observer)
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
                try {
                    $pengaturan = PengaturanSekolah::first();
                } catch (\Throwable $e2) {
                    $pengaturan = null;
                }
                if (! $pengaturan) {
                    Log::warning('AuditLogArchive auto-create pengaturan gagal: '.$e->getMessage());
                }
            }

            DB::transaction(function () use ($cutoff, $now, &$archivedCount) {
                $toArchive = AuditLog::where('created_at', '<', $cutoff)->orderBy('id')->get();

                if ($toArchive->isEmpty()) {
                    return;
                }

                $rows = [];
                foreach ($toArchive as $log) {
                    $rows[] = [
                        'id' => $log->id,
                        'user_id' => $log->user_id,
                        'action' => $log->action,
                        'auditable_type' => $log->auditable_type,
                        'auditable_id' => $log->auditable_id,
                        'description' => $log->description,
                        'old_values' => $log->old_values ? json_encode($log->old_values) : null,
                        'new_values' => $log->new_values ? json_encode($log->new_values) : null,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at,
                        'updated_at' => $log->updated_at,
                        'archived_at' => $now,
                    ];
                }

                // Chunk insert 500
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('audit_log_archives')->insert($chunk);
                }

                $ids = $toArchive->pluck('id')->all();
                AuditLog::whereIn('id', $ids)->delete();
                $archivedCount = count($ids);
            });

            if ($pengaturan) {
                try {
                    // Jangan trigger LogsActivity (audit) untuk update internal timestamp
                    if (method_exists($pengaturan, 'saveQuietly')) {
                        $pengaturan->forceFill(['audit_last_archive_at' => $now])->saveQuietly();
                    } else {
                        DB::table('pengaturan_sekolah')->where('id', $pengaturan->id)->update(['audit_last_archive_at' => $now, 'updated_at' => $now]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('AuditLogArchive simpan timestamp gagal: '.$e->getMessage());
                }
            }

            $remaining = 0;
            try {
                $remaining = AuditLog::count();
            } catch (\Throwable $e) {
            }

            return ['archived' => $archivedCount, 'remaining' => $remaining];
        } catch (\Throwable $e) {
            Log::warning('AuditLogArchive runArchive exception: '.$e->getMessage());

            return ['archived' => 0, 'remaining' => 0, 'error' => $e->getMessage()];
        }
    }

    public static function getArchiveCount(): int
    {
        try {
            return AuditLogArchive::count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function getActiveCount(): int
    {
        try {
            return AuditLog::count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function getLastArchiveAt(): ?Carbon
    {
        try {
            $row = PengaturanSekolah::first();
            $val = $row?->audit_last_archive_at;
            if ($val === null) {
                return null;
            }

            return $val instanceof Carbon ? $val : Carbon::parse($val);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

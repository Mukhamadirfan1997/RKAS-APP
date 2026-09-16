<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Services\AuditLogArchiveService;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->take(500)
            ->get();

        $archiveCount = 0;
        $activeCount = $logs->count();
        $lastArchiveAt = null;
        $archiveLogs = collect();
        try {
            $archiveCount = AuditLogArchiveService::getArchiveCount();
            $activeCount = AuditLogArchiveService::getActiveCount();
            $lastArchiveAt = AuditLogArchiveService::getLastArchiveAt();
            if ($archiveCount > 0) {
                $archiveLogs = AuditLogArchive::with('user')
                    ->orderByDesc('created_at')
                    ->take(100)
                    ->get();
            }
        } catch (\Throwable $e) {
        }

        return view('audit.index', compact('logs', 'archiveCount', 'activeCount', 'lastArchiveAt', 'archiveLogs'));
    }
}

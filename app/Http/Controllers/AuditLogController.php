<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::with('user')
            ->orderByDesc('created_at')
            ->take(500)
            ->get();

        return view('audit.index', compact('logs'));
    }
}

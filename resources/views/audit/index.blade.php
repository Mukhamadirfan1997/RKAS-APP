@extends('layouts.app')

@section('content')
@php
    $actionBadge = [
        'created' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30',
        'updated' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30',
        'deleted' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
        'login' => 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700/60 dark:text-slate-300 dark:border-slate-600',
        'import' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/30',
    ];
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">Riwayat Aktivitas (Audit Log)</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Catatan tambah/ubah/hapus data &mdash; bagian dari pengamanan RKAS referensi.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700/60 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $logs->count() }} Log Terakhir</h3>
            <span class="text-[11px] text-slate-400 dark:text-slate-500">Otomatis tercatat oleh sistem</span>
        </div>
        <div class="max-h-[560px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
            @forelse($logs as $log)
                <div class="px-5 py-3.5 flex items-start justify-between gap-3 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <div class="flex items-start gap-3">
                        <span class="px-2 py-1 mt-0.5 rounded-full text-[10px] font-bold border {{ $actionBadge[$log->action] ?? 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-700/60 dark:text-slate-300 dark:border-slate-600' }}">{{ strtoupper($log->action) }}</span>
                        <div>
                            <div class="text-sm text-slate-700 dark:text-slate-200">{{ $log->description }}</div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                {{ \Illuminate\Support\Str::afterLast($log->auditable_type, '\\') }}
                                @if($log->auditable_id) #{{ $log->auditable_id }} @endif
                                &middot; {{ $log->user->name ?? 'Sistem' }}
                                &middot; {{ $log->ip_address }}
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0">{{ $log->created_at->format('d M Y H:i:s') }}</div>
                </div>
            @empty
                <div class="px-4 py-12 text-center text-xs text-slate-400 dark:text-slate-500">Belum ada aktivitas tercatat.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

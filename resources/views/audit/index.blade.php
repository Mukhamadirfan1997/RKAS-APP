@extends('layouts.app')

@section('content')
@php
    $actionBadge = [
        'created' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'updated' => 'bg-blue-50 text-blue-700 border-blue-200',
        'deleted' => 'bg-red-50 text-red-700 border-red-200',
        'login' => 'bg-slate-50 text-slate-600 border-slate-200',
        'import' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    ];
@endphp

<div class="p-5 md:p-6 max-w-[1440px] mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-lg md:text-xl font-bold text-slate-800">Riwayat Aktivitas (Audit Log)</h1>
            <p class="text-xs text-slate-500 mt-0.5">Catatan tambah/ubah/hapus data &mdash; bagian dari pengamanan RKAS referensi.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700">{{ $logs->count() }} Log Terakhir</h3>
            <span class="text-[11px] text-slate-400">Otomatis tercatat oleh sistem</span>
        </div>
        <div class="max-h-[560px] overflow-y-auto divide-y divide-slate-100">
            @forelse($logs as $log)
                <div class="px-4 py-3 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="px-2 py-1 mt-0.5 rounded-full text-[10px] font-bold border {{ $actionBadge[$log->action] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">{{ strtoupper($log->action) }}</span>
                        <div>
                            <div class="text-sm text-slate-700">{{ $log->description }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                {{ \Illuminate\Support\Str::afterLast($log->auditable_type, '\\') }}
                                @if($log->auditable_id) #{{ $log->auditable_id }} @endif
                                &middot; {{ $log->user->name ?? 'Sistem' }}
                                &middot; {{ $log->ip_address }}
                            </div>
                        </div>
                    </div>
                    <div class="text-[10px] text-slate-400 shrink-0">{{ $log->created_at->format('d M Y H:i:s') }}</div>
                </div>
            @empty
                <div class="px-4 py-12 text-center text-xs text-slate-400">Belum ada aktivitas tercatat.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
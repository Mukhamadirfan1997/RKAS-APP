@php $cur = request()->route()->getName(); @endphp
<div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200 dark:border-slate-700/60 pb-3">
    <a href="{{ route('pengaturan.profil') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $cur==='pengaturan.profil' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">1. Profil Sekolah</a>
    <a href="{{ route('pengaturan.tahun.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $cur==='pengaturan.tahun.index' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">2. Tahun Anggaran</a>
    <a href="{{ route('pengaturan.pagu', ['tahun'=>$tahunAnggaran->tahun ?? (int) date('Y')]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ in_array($cur, ['pengaturan.pagu','pengaturan.status'], true) ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">3. Pagu &amp; Status RKAS</a>
    <a href="{{ route('pengaturan.akun') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $cur==='pengaturan.akun' ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">4. Akun</a>
</div>

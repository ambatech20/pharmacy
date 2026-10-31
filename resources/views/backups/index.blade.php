@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-2">Backups</h1>
    <p class="text-sm text-[#64748b] mb-6">
        A backup is a single file holding everything in the system: your medicines and products,
        purchases, sales, customers, suppliers, accounts and settings. Download one regularly and
        keep it somewhere other than this computer's Downloads folder.
    </p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">{{ session('error') }}</div>
    @endif

    @if($warning)
        <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <p class="text-sm text-[#92400e]">{{ $warning }}</p>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-semibold text-[#1E2530] mb-1">Download a backup</h2>
        <p class="text-sm text-[#64748b] mb-4">
            Your browser will save one <span class="font-medium text-[#1E2530]">.sql</span> file.
            It takes a few seconds. Nothing in the system changes when you do this — it only reads.
        </p>

        <a href="{{ route('backups.download') }}"
           class="inline-flex items-center gap-2 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-3 px-6 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
            </svg>
            Download backup now
        </a>

        <p class="text-xs text-[#64748b] mt-4">
            @if($lastBackupAt)
                Last backup taken: <span class="font-medium text-[#1E2530]">{{ $lastBackupAt }}</span>
                @if($lastBackupBy) by {{ $lastBackupBy }}@endif.
            @else
                No backup has been taken from this screen yet.
            @endif
        </p>
    </div>

    @if(count($retained))
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mt-4">
            <h2 class="text-base font-semibold text-[#1E2530] mb-1">Copies kept on the server</h2>
            <p class="text-sm text-[#64748b] mb-4">
                The last {{ count($retained) }} {{ count($retained) === 1 ? 'backup is' : 'backups are' }} also kept
                on the server itself. These are a safety net, not a substitute for a copy you hold:
                they sit on the same machine as the website. Older ones are cleared automatically.
            </p>

            <ul class="divide-y divide-gray-100 border-t border-gray-100">
                @foreach($retained as $backup)
                    <li class="flex items-center justify-between gap-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#1E2530] truncate">{{ $backup['taken_at']->format('D, d M Y · H:i') }}</p>
                            <p class="text-xs text-[#64748b]">
                                {{ $backup['bytes'] >= 1048576
                                    ? number_format($backup['bytes'] / 1048576, 1) . ' MB'
                                    : number_format($backup['bytes'] / 1024, 0) . ' KB' }}
                            </p>
                        </div>
                        <a href="{{ $backup['url'] }}"
                           class="flex-shrink-0 text-sm font-medium text-[var(--brand-primary)] hover:underline">Download</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mt-4">
        <h2 class="text-base font-semibold text-[#1E2530] mb-2">How often?</h2>
        <p class="text-sm text-[#64748b]">
            Once a week is plenty for most pharmacies, and once a month is the absolute minimum.
            A useful habit: download one at closing time on the last day of each month and keep it
            with your paper records. A reminder appears on the dashboard once it has been more than
            two weeks.
        </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mt-4">
        <h2 class="text-base font-semibold text-[#1E2530] mb-2">Where to keep the file</h2>
        <ul class="text-sm text-[#64748b] list-disc pl-5 space-y-1">
            <li>A USB stick that stays in the pharmacy, or</li>
            <li>Emailed to yourself, or</li>
            <li>Your own cloud drive</li>
        </ul>
        <p class="text-xs text-[#64748b] mt-3">
            Do not rely on the Downloads folder alone — it gets cleared, and it lives on the same
            computer either way. The backup contains your staff accounts, so treat the file like a
            password and do not share it.
        </p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mt-4">
        <h2 class="text-base font-semibold text-[#92400e] mb-2">If you ever need to restore it</h2>
        <ol class="text-sm text-[#92400e]/90 list-decimal pl-5 space-y-1">
            <li>Sign in to your hosting control panel.</li>
            <li>Open <span class="font-medium">Databases</span> and click the database this installation uses.</li>
            <li>Click <span class="font-medium">Import dump</span>, choose the .sql file, and confirm.</li>
        </ol>
        <p class="text-xs text-[#92400e]/90 mt-3">
            <span class="font-semibold">Careful:</span> restoring replaces everything currently in the
            system with what is in the file. Anything entered after that backup was taken will be lost.
            Only do this if data has genuinely been damaged, and ask someone technical first if you
            are unsure.
        </p>
    </div>
</div>
@endsection

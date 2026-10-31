@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('settings.branding') }}" class="text-sm text-[var(--brand-primary)] hover:underline">Settings</a>
        <span class="text-[#64748b]">/</span>
        <span class="text-sm text-[#64748b]">P-Check Integration</span>
    </div>

    <h1 class="text-2xl font-bold text-[#1E2530] mb-2">P-Check Integration</h1>
    <p class="text-sm text-[#64748b] mb-6">Connect to P-Check to automatically verify digital payments (Telebirr, CBE Birr, Bank Transfer, Card) against the provider.</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3 mb-4">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('settings.integration.update') }}"
          autocomplete="off" data-form-type="other"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between">
            <div>
                <label class="text-sm font-medium text-[#1E2530]">Enable P-Check</label>
                <p class="text-xs text-[#64748b] mt-0.5">When off, verification badges are hidden and webhooks are rejected.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="payment_verifier_enabled" value="0">
                <input type="checkbox" name="payment_verifier_enabled" value="1"
                       class="sr-only peer"
                       {{ old('payment_verifier_enabled', $settings['payment_verifier_enabled']) ? 'checked' : '' }}>
                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[var(--brand-primary)]/30 rounded-full peer peer-checked:after:translate-x-5 peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[var(--brand-primary)]"></div>
            </label>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Webhook Secret</label>
            <p class="text-xs text-[#64748b] mb-2">
                Paste the same secret you configured in P-Check's integration settings. Used to verify incoming webhook signatures.
                <span class="block mt-1 text-[#92400e]">Required to turn the integration on. Without it the webhook refuses every request, because it would have no way to tell P-Check from anyone else.</span>
            </p>
            <div class="flex gap-2">
                <input type="text" name="payment_verifier_webhook_secret"
                       value="{{ old('payment_verifier_webhook_secret', $settings['payment_verifier_webhook_secret']) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                       placeholder="e.g. whsec_a1b2c3d4..."
                       @error('payment_verifier_webhook_secret') aria-invalid="true" @enderror
                       class="flex-1 border border-gray-200 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                <button type="button" onclick="generateSecret()" class="px-3 py-2.5 text-xs font-medium text-[var(--brand-primary)] border border-gray-200 rounded-lg hover:bg-gray-50 whitespace-nowrap">
                    Generate
                </button>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4 space-y-2">
            <p class="text-xs font-semibold text-[#64748b] uppercase tracking-wide">Webhook URL</p>
            <p class="text-sm font-mono text-[#1E2530] break-all">{{ $webhookUrl }}</p>
            <p class="text-xs text-[#64748b]">Enter this URL in P-Check when registering this pharmacy as an integration endpoint.</p>
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="w-full bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-4 rounded-lg transition-colors">
                Save Integration Settings
            </button>
        </div>
    </form>

    <div class="mt-6 bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-sm font-semibold text-[#1E2530] mb-3">How it works</h2>
        <ol class="space-y-2 text-sm text-[#64748b]">
            <li class="flex gap-2">
                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-xs font-bold flex items-center justify-center">1</span>
                When creating a sale with a digital payment, enter the provider transaction reference.
            </li>
            <li class="flex gap-2">
                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-xs font-bold flex items-center justify-center">2</span>
                P-Check verifies the transaction with the payment provider.
            </li>
            <li class="flex gap-2">
                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-xs font-bold flex items-center justify-center">3</span>
                A green checkmark appears on verified sales in the dispense history and sales report.
            </li>
        </ol>
    </div>
</div>

<script>
function generateSecret() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    const secret = 'whsec_' + Array.from(bytes)
        .map(b => b.toString(16).padStart(2, '0'))
        .join('')
        .substring(0, 48);
    document.querySelector('input[name="payment_verifier_webhook_secret"]').value = secret;
}
</script>
@endsection

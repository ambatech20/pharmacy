@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-6">Business Settings</h1>
    <p class="text-sm text-[#64748b] mb-6 -mt-4">Set your pharmacy's name, logo and colors here. They appear on the sidebar, login page, browser title and receipts.</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data"
          autocomplete="off" data-form-type="other"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Business Name</label>
            <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name']) }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Phone</label>
            <input type="text" name="business_phone" value="{{ old('business_phone', $settings['business_phone']) }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Address</label>
            <textarea name="business_address" rows="2"
                      autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                      data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-dashlane-ignore="true" data-protonpass-ignore="true" data-form-type="other"
                      class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">{{ old('business_address', $settings['business_address']) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Main Color</label>
                <div class="flex gap-2">
                    <input type="color" name="brand_primary_color_picker"
                           value="{{ old('brand_primary_color', $settings['brand_primary_color']) }}"
                           onchange="this.form.brand_primary_color.value = this.value"
                           class="h-11 w-11 rounded-lg border border-gray-200 cursor-pointer p-1">
                    <input type="text" name="brand_primary_color" value="{{ old('brand_primary_color', $settings['brand_primary_color']) }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Accent Color</label>
                <div class="flex gap-2">
                    <input type="color" name="brand_accent_color_picker"
                           value="{{ old('brand_accent_color', $settings['brand_accent_color']) }}"
                           onchange="this.form.brand_accent_color.value = this.value"
                           class="h-11 w-11 rounded-lg border border-gray-200 cursor-pointer p-1">
                    <input type="text" name="brand_accent_color" value="{{ old('brand_accent_color', $settings['brand_accent_color']) }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Logo (JPG, PNG or WebP, max 2 MB)</label>
            @if($settings['logo_path'])
                <div class="flex items-center gap-3 mb-2">
                    <img src="{{ Storage::url($settings['logo_path']) }}" alt="Current logo" class="h-12 w-12 object-contain rounded border border-gray-200 bg-white p-1">
                    <label class="flex items-center gap-2 text-sm text-[#64748b] min-h-[44px]">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300"> Remove current logo
                    </label>
                </div>
            @endif
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--brand-primary)]/10 file:text-[var(--brand-primary)] file:text-sm file:font-medium">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Receipt Header Text</label>
            <input type="text" name="receipt_header" value="{{ old('receipt_header', $settings['receipt_header']) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30"
                   placeholder="e.g. Thank you for your purchase!">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Receipt Footer Text</label>
            <input type="text" name="receipt_footer" value="{{ old('receipt_footer', $settings['receipt_footer']) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30"
                   placeholder="e.g. Goods once sold are not returnable.">
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">VAT included in your selling prices (%)</label>
            <input type="number" step="0.01" min="0" max="99.99" name="vat_rate_percent" value="{{ old('vat_rate_percent', $settings['vat_rate_percent']) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            <p class="text-xs text-[#64748b] mt-1">Your prices already contain VAT, so nothing changes for the customer — receipts just show how much of each price is VAT. In Ethiopia that is 15. Enter 0 to hide the VAT lines.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Payment reminder window (days)</label>
            <input type="number" min="1" max="90" name="supplier_alert_days" value="{{ old('supplier_alert_days', $settings['supplier_alert_days']) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            <p class="text-xs text-[#64748b] mt-1">Supplier payments due within this many days show as reminders on Alerts and the dashboard.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Expiry alert window</label>
            <select name="expiry_alert_days" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
                @foreach(\App\Models\Setting::EXPIRY_ALERT_WINDOWS as $window)
                    <option value="{{ $window }}" @selected((int) old('expiry_alert_days', $settings['expiry_alert_days']) === $window)>{{ $window }} days</option>
                @endforeach
            </select>
            <p class="text-xs text-[#64748b] mt-1">How far ahead the "expiring soon" alert looks. This is the default window; staff can still preview 30, 60 or 90 days on the Alerts page.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">Save Settings</button>
            <a href="{{ route('dashboard') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>
@endsection

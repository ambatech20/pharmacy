@php
    // PART D — receipt branding from settings (nothing hard-coded).
    $receiptBusinessName = \App\Models\Setting::value('business_name', config('pmas.default_business_name'));
    $brandPrimary = \App\Models\Setting::value('brand_primary_color', config('pmas.default_brand_primary_color'));
    $brandAccent = \App\Models\Setting::value('brand_accent_color', config('pmas.default_brand_accent_color'));
    $receiptHeader = \App\Models\Setting::value('receipt_header');
    $receiptFooter = \App\Models\Setting::value('receipt_footer');
    // Business-wide contact from settings (single-branch install, so there is
    // no per-branch address to fall back to).
    $receiptAddress = \App\Models\Setting::value('business_address');
    $receiptPhone = \App\Models\Setting::value('business_phone');
    // Prices charged include VAT; the breakdown is printed, not charged again.
    $vat = $order->vatBreakdown();
@endphp
<!DOCTYPE html>
<html lang="en" style="@include('partials.brand-vars')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $order->id }} | {{ $receiptBusinessName }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #F5F7FA;
            color: #1E2530;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        .toolbar {
            max-width: 80mm;
            margin: 16px auto 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .toolbar a, .toolbar button {
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-print { background: var(--brand-primary); color: #fff; }
        .btn-ghost { background: #fff; color: #1E2530; border: 1px solid #e5e7eb; }
        .receipt {
            width: 80mm;
            margin: 0 auto 24px;
            background: #fff;
            padding: 10px 12px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .center { text-align: center; }
        .name { font-size: 16px; font-weight: 700; }
        .muted { color: #64748b; font-size: 11px; }
        .fine { color: #64748b; font-size: 10px; }
        hr { border: 0; border-top: 1px dashed #d1d5db; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; white-space: nowrap; }
        .total { font-weight: 700; font-size: 14px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { box-shadow: none; margin: 0; width: 72mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print receipt</button>
        <a class="btn-ghost" href="{{ route('dispense.receipt.escpos', $order) }}">Download ESC/POS</a>
        <a class="btn-ghost" href="{{ route('dispense.show', $order) }}">Back</a>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="name">{{ $receiptBusinessName }}</div>
            @if($receiptHeader)
                <div class="muted">{{ $receiptHeader }}</div>
            @endif
            @if($receiptAddress)
                <div class="muted">{{ $receiptAddress }}</div>
            @endif
            @if($receiptPhone)
                <div class="muted">{{ $receiptPhone }}</div>
            @endif
        </div>
        <hr>
        <table>
            <tr><td>Order</td><td class="right">#{{ $order->id }}</td></tr>
            <tr><td>Date</td><td class="right">{{ $order->created_at->format('M d, Y g:i A') }}</td></tr>
            <tr><td>Cashier</td><td class="right">{{ $order->user->name ?? '—' }}</td></tr>
            <tr><td>Customer</td><td class="right">{{ $order->customer->name ?? 'Walk-in' }}</td></tr>
        </table>
        <hr>
        <table>
            @foreach($order->items as $item)
                <tr>
                    <td colspan="2">{{ $item->purchaseBatch->drug->name ?? $item->product->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="muted">{{ $item->quantity_issued }} × ${{ number_format($item->selling_price_per_unit, 2) }}@if($vat) (incl. VAT) @endif</td>
                    <td class="right">${{ number_format($item->total_amount, 2) }}</td>
                </tr>

            @endforeach
        </table>
        <hr>
        <table>
            @if($vat)
                <tr>
                    <td class="muted">Before VAT</td>
                    <td class="right">${{ number_format($vat['net'], 2) }}</td>
                </tr>
                <tr>
                    <td class="muted">VAT {{ $vat['rate_label'] }}%</td>
                    <td class="right">${{ number_format($vat['vat'], 2) }}</td>
                </tr>
                <tr class="total">
                    <td>Total (incl. VAT)</td>
                    <td class="right">${{ number_format($vat['total'], 2) }}</td>
                </tr>
            @else
                <tr class="total">
                    <td>Total</td>
                    <td class="right">${{ number_format($order->total_amount, 2) }}</td>
                </tr>
            @endif
            @foreach($order->payments as $payment)
                <tr>
                    <td class="muted">{{ $payment->methodLabel() }}</td>
                    <td class="right">${{ number_format($payment->amount, 2) }}</td>
                </tr>
            @endforeach
        </table>

        <hr>
        <div class="center muted">{{ $receiptFooter ?? 'Thank you' }}</div>
    </div>

    @if(request()->boolean('autoprint'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>

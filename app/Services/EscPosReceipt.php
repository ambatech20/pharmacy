<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;

/**
 * Builds a raw ESC/POS byte string for 80mm thermal printers.
 * The HTML receipt is what cashiers actually print from the browser
 * (Windows POS-80 driver). This download is for a raw USB/network
 * printer that wants ESC/POS instead of a Windows driver.
 */
class EscPosReceipt
{
    private const WIDTH = 42;

    public function forOrder(Order $order): string
    {
        $ESC = "\x1B";
        $GS = "\x1D";

        // PART D — business name and receipt header/footer come from settings.
        $businessName = Setting::value('business_name', config('pmas.default_business_name'));
        $header = Setting::value('receipt_header');
        $footer = Setting::value('receipt_footer');
        // Business-wide contact from settings; the branch's own details are the fallback.
        $address = Setting::value('business_address') ?: ($order->branch?->address);
        $phone = Setting::value('business_phone') ?: ($order->branch?->phone);
        // Prices charged include VAT; the receipt shows how much of them is VAT.
        $vat = $order->vatBreakdown();

        $out = $ESC . '@';
        $out .= $ESC . 'a' . chr(1);
        $out .= $ESC . '!' . chr(0x18);
        $out .= $this->line($businessName);
        $out .= $ESC . '!' . chr(0);
        $out .= $this->line($order->branch->name ?? 'Pharmacy');
        if ($header) {
            $out .= $this->line($header);
        }
        if ($address) {
            $out .= $this->line($address);
        }
        if ($phone) {
            $out .= $this->line($phone);
        }
        $out .= $this->line(str_repeat('-', self::WIDTH));

        $out .= $ESC . 'a' . chr(0);
        $out .= $this->line('Order #' . $order->id);
        $out .= $this->line($order->created_at->format('Y-m-d H:i'));
        $out .= $this->line('Cashier: ' . ($order->user->name ?? '—'));
        $out .= $this->line('Customer: ' . ($order->customer->name ?? 'Walk-in'));
        $out .= $this->line(str_repeat('-', self::WIDTH));

        foreach ($order->items as $item) {
            $name = $item->purchaseBatch->drug->name ?? $item->product->name ?? 'Item';
            $qty = (int) $item->quantity_issued;
            $total = number_format((float) $item->total_amount, 2);
            $out .= $this->line($name);
            $out .= $this->cols($qty . ' x ' . number_format((float) $item->selling_price_per_unit, 2) . ($vat ? ' incl. VAT' : ''), $total);
            if ($vat) {
                $out .= $this->line('  VAT ' . number_format($vat['items'][$item->id]['vat'], 2));
            }
        }

        $out .= $this->line(str_repeat('-', self::WIDTH));
        if ($vat) {
            $out .= $this->cols('Before VAT', number_format($vat['net'], 2));
            $out .= $this->cols('VAT ' . $vat['rate_label'] . '%', number_format($vat['vat'], 2));
            $out .= $ESC . '!' . chr(0x08);
            $out .= $this->cols('TOTAL (incl. VAT)', number_format($vat['total'], 2));
            $out .= $ESC . '!' . chr(0);
            $out .= $this->line('VAT = ' . number_format($vat['total'], 2) . ' x ' . $vat['rate_label'] . '/' . $vat['divisor_label'] . ' = ' . number_format($vat['vat'], 2));
        } else {
            $out .= $ESC . '!' . chr(0x08);
            $out .= $this->cols('TOTAL', number_format((float) $order->total_amount, 2));
            $out .= $ESC . '!' . chr(0);
        }

        foreach ($order->payments as $payment) {
            $out .= $this->cols($payment->methodLabel(), number_format((float) $payment->amount, 2));
        }

        $out .= $this->line(str_repeat('-', self::WIDTH));
        $out .= $ESC . 'a' . chr(1);
        $out .= $this->line($footer ?: 'Thank you');
        $out .= "\n\n\n";
        $out .= $GS . 'V' . chr(66) . chr(3);

        return $out;
    }

    private function line(string $text): string
    {
        return substr($text, 0, self::WIDTH) . "\n";
    }

    private function cols(string $left, string $right): string
    {
        $space = self::WIDTH - strlen($left) - strlen($right);
        if ($space < 1) {
            $left = substr($left, 0, self::WIDTH - strlen($right) - 1);
            $space = 1;
        }

        return $left . str_repeat(' ', $space) . $right . "\n";
    }
}

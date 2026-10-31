<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EscPosReceipt;

class ReceiptController extends Controller
{
    public function show(Order $order)
    {
        $this->authorizeOrder($order);
        $order->load(['customer', 'branch', 'user', 'items.purchaseBatch.drug', 'payments']);

        return view('dispense.receipt', compact('order'));
    }

    public function escpos(Order $order, EscPosReceipt $escPos)
    {
        $this->authorizeOrder($order);
        $order->load(['customer', 'branch', 'user', 'items.purchaseBatch.drug', 'payments']);

        $bytes = $escPos->forOrder($order);

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="receipt-' . $order->id . '.bin"',
        ]);
    }

    private function authorizeOrder(Order $order): void
    {
        // Same branch gate as DispenseController::show — a guessed
        // receipt URL must not leak another branch's sale.
        $user = auth()->user();
        if ($user->branch_id && $order->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to this order.');
        }
    }
}

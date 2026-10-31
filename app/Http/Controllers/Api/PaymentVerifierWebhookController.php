<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentVerifierWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (Setting::value('payment_verifier_enabled', '0') !== '1') {
            return response()->json(['error' => 'Integration disabled'], 403);
        }

        $secret = Setting::value('payment_verifier_webhook_secret')
            ?: config('services.payment_verifier.webhook_secret');

        // Fail closed. The integration is switched on but no shared secret is
        // configured, which used to skip verification entirely and leave an
        // unauthenticated endpoint that could mark any real payment as
        // verified. Refusing is the safe direction: the worst outcome is a
        // verifier that cannot reach us, which the owner notices immediately.
        if (! $secret) {
            Log::error('Payment verifier webhook called while no secret is configured', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Integration is enabled but no webhook secret is configured.',
            ], 503);
        }

        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $payload, $secret);

        if (! $signature || ! hash_equals($expected, $signature)) {
            Log::warning('Payment verifier webhook signature mismatch', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = $request->validate([
            'event' => 'required|string',
            'data' => 'required|array',
            'data.transaction_id' => 'required|string',
            'data.status' => 'required|string',
        ]);

        $transactionId = $data['data']['transaction_id'];
        $status = $data['data']['status'];

        $payment = Payment::where('transaction_reference', $transactionId)->first();

        if (!$payment) {
            Log::info('Payment verifier webhook: no payment found', [
                'transaction_id' => $transactionId,
            ]);
            return response()->json(['status' => 'ignored', 'reason' => 'no_matching_payment'], 200);
        }

        $verificationStatus = match ($status) {
            'verified' => 'verified',
            'failed', 'not_found', 'mismatch', 'manual_review' => 'declined',
            default => 'pending',
        };

        $payment->update(['verification_status' => $verificationStatus]);

        Log::info('Payment verification status updated', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'transaction_id' => $transactionId,
            'status' => $verificationStatus,
        ]);

        return response()->json(['status' => 'ok']);
    }
}

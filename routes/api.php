<?php

use App\Http\Controllers\Api\PaymentVerifierWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/payment-verifier/webhook', [PaymentVerifierWebhookController::class, 'handle'])
    ->name('api.payment-verifier.webhook');

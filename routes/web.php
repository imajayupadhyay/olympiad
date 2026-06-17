<?php

use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// Razorpay server-to-server webhook (no auth; CSRF-exempt in bootstrap/app.php)
Route::post('/razorpay/webhook', RazorpayWebhookController::class)->name('razorpay.webhook');

// Auth routes (Breeze — login, register, password reset, email verify)
require __DIR__.'/auth.php';

// Public marketing website
require __DIR__.'/public.php';

// Student portal (auth + verified required — defined inside student.php)
require __DIR__.'/student.php';

// Admin portal (auth + admin role required — defined inside admin.php)
require __DIR__.'/admin.php';

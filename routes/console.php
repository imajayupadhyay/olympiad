<?php

use App\Models\LoginOtpChallenge;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('emails:send-exam-reminders --hours=24')->hourly();

Schedule::command('payments:reconcile-razorpay')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::call(function (): void {
    LoginOtpChallenge::where('created_at', '<', now()->subDay())->delete();
})->dailyAt('02:20')->name('prune-login-otp-challenges')->withoutOverlapping();

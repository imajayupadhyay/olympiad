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

// Exam sessions whose deadline passed while the student was away get scored (as
// timed_out) so they reach results; admins can still reopen them from Ongoing Exams.
Schedule::command('exam-attempts:finalize-expired')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

// Admin broadcasts: resume stalled email/WhatsApp sends and pull WhatsApp
// delivered/read/failed status from Bird (workflow.md §13.2).
Schedule::command('notifications:maintain')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::call(function (): void {
    LoginOtpChallenge::where('created_at', '<', now()->subDay())->delete();
})->dailyAt('02:20')->name('prune-login-otp-challenges')->withoutOverlapping();

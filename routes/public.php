<?php

use App\Http\Controllers\Public\ExamController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MarketingController;
use App\Http\Controllers\Public\SchoolController;
use App\Http\Controllers\Public\SyllabusController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', function () {
    $siteUrl = rtrim(config('app.url'), '/');
    $urls = [
        [
            'loc' => $siteUrl.'/',
            'lastmod' => now()->toDateString(),
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
        [
            'loc' => $siteUrl.'/marketing',
            'lastmod' => now()->toDateString(),
            'changefreq' => 'weekly',
            'priority' => '0.9',
        ],
        [
            'loc' => $siteUrl.'/syllabus',
            'lastmod' => now()->toDateString(),
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ],
    ];

    return response()
        ->view('sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/exams', [ExamController::class, 'index'])->name('exams');
Route::get('/syllabus', [SyllabusController::class, 'index'])->name('syllabus');
Route::post('/exams/enroll', [ExamController::class, 'enroll'])->name('exams.enroll');
Route::get('/results', [HomeController::class, 'results'])->name('results');
Route::get('/blog', [HomeController::class, 'blog'])->name('blog');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'submitContact'])->name('contact.store');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');

/*
 * Campaign landing page — a single-CTA funnel that registers the student, takes
 * their olympiad picks and a referral code, and collects payment without ever
 * leaving the page. See App\Services\MarketingFunnelService.
 */
Route::get('/marketing', [MarketingController::class, 'index'])->name('marketing');
Route::post('/marketing/register', [MarketingController::class, 'register'])
    ->middleware('throttle:8,1')->name('marketing.register');
Route::post('/marketing/payment/{payment}/callback', [MarketingController::class, 'paymentCallback'])
    ->name('marketing.payment.callback');
Route::middleware('auth')->group(function () {
    Route::post('/marketing/payment/{payment}/order', [MarketingController::class, 'createOrder'])
        ->name('marketing.payment.order');
    Route::post('/marketing/payment/{payment}/verify', [MarketingController::class, 'verifyPayment'])
        ->name('marketing.payment.verify');
});

// School autocomplete for the registration form (public — no auth).
Route::get('/schools/search', [SchoolController::class, 'search'])->name('schools.search');

<?php

use App\Http\Controllers\Public\ExamController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SchoolController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/exams', [ExamController::class, 'index'])->name('exams');
Route::post('/exams/enroll', [ExamController::class, 'enroll'])->name('exams.enroll');
Route::get('/results', [HomeController::class, 'results'])->name('results');
Route::get('/blog', [HomeController::class, 'blog'])->name('blog');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'submitContact'])->name('contact.store');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');

// School autocomplete for the registration form (public — no auth).
Route::get('/schools/search', [SchoolController::class, 'search'])->name('schools.search');

<?php

use App\Http\Controllers\Public\ExamController;
use App\Http\Controllers\Public\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/exams', [ExamController::class, 'index'])->name('exams');
Route::post('/exams/enroll', [ExamController::class, 'enroll'])->name('exams.enroll');
Route::get('/results', [HomeController::class, 'results'])->name('results');
Route::get('/blog', [HomeController::class, 'blog'])->name('blog');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/pricing', [HomeController::class, 'pricing'])->name('pricing');
Route::get('/faq', [HomeController::class, 'faq'])->name('faq');

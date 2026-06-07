<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\Settings\ClassLevelController;
use App\Http\Controllers\Admin\Settings\QuestionCategoryController;
use App\Http\Controllers\Admin\Settings\SubjectController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// ── Secure Admin Login (obfuscated URL) ──────────────────────────────────────
Route::get('/olympiad-secure-login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/olympiad-secure-login', [AuthController::class, 'login'])->name('admin.login.submit');

// ── Admin Logout ──────────────────────────────────────────────────────────────
Route::post('/admin/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'admin'])
    ->name('admin.logout');

// ── Protected Admin Panel ─────────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::resource('questions', QuestionController::class);
    Route::resource('exams', ExamController::class);
    Route::patch('/exams/{exam}/publish', [ExamController::class, 'publish'])->name('exams.publish');
    Route::patch('/exams/{exam}/unpublish', [ExamController::class, 'unpublish'])->name('exams.unpublish');
    Route::patch('/exams/{exam}/archive', [ExamController::class, 'archive'])->name('exams.archive');

    Route::get('/results', [ResultController::class, 'index'])->name('results');
    Route::get('/results/{exam}', [ResultController::class, 'show'])->name('results.show');
    Route::post('/results/{exam}/process', [ResultController::class, 'process'])->name('results.process');
    Route::post('/results/{exam}/release', [ResultController::class, 'release'])->name('results.release');
    Route::patch('/results/override/{result}', [ResultController::class, 'override'])->name('results.override');

    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates');
    Route::get('/certificates/{exam}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::post('/certificates/{exam}/upload', [CertificateController::class, 'upload'])->name('certificates.upload');
    Route::post('/certificates/{exam}/generate', [CertificateController::class, 'generate'])->name('certificates.generate');
    Route::delete('/certificates/{exam}/template', [CertificateController::class, 'deleteTemplate'])->name('certificates.template.delete');
    Route::get('/certificates/{exam}/download', [CertificateController::class, 'download'])->name('certificates.download');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send');
    Route::delete('/notifications/{log}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/content', [ContentController::class, 'index'])->name('content');

    // ── Settings ────────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects');
        Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

        Route::get('/categories', [QuestionCategoryController::class, 'index'])->name('categories');
        Route::post('/categories', [QuestionCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [QuestionCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [QuestionCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/classes', [ClassLevelController::class, 'index'])->name('classes');
        Route::post('/classes', [ClassLevelController::class, 'store'])->name('classes.store');
        Route::put('/classes/{classLevel}', [ClassLevelController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{classLevel}', [ClassLevelController::class, 'destroy'])->name('classes.destroy');
    });
});

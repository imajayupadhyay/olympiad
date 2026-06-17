<?php

use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\ExamRoomController;
use App\Http\Controllers\Student\ResultController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\LeaderboardController;
use App\Http\Controllers\Student\PracticeController;
use App\Http\Controllers\Student\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');

    Route::get('/exams', [ExamController::class, 'index'])->name('exams');
    Route::post('/exams/enroll', [ExamController::class, 'enroll'])->name('exams.enroll');
    Route::get('/exams/resume-enroll', [ExamController::class, 'resumeEnroll'])->name('exams.resume-enroll');
    Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');

    Route::post('/exams/{exam}/start', [ExamRoomController::class, 'start'])->name('exams.start');
    Route::get('/exam-room/{attempt}', [ExamRoomController::class, 'index'])->name('exam-room');
    Route::post('/exam-room/{attempt}/answer', [ExamRoomController::class, 'saveAnswer'])->name('exam-room.answer');
    Route::post('/exam-room/{attempt}/submit', [ExamRoomController::class, 'submit'])->name('exam-room.submit');
    Route::get('/exam-room/{attempt}/submitted', [ExamRoomController::class, 'submitted'])->name('exam-room.submitted');

    Route::get('/results', [ResultController::class, 'index'])->name('results');
    Route::get('/results/{result}', [ResultController::class, 'show'])->name('results.show');

    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('/practice', [PracticeController::class, 'index'])->name('practice');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
});

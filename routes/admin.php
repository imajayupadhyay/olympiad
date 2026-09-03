<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataEntryController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\ReferralSettingController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolDesignationController;
use App\Http\Controllers\Admin\Settings\ClassLevelController;
use App\Http\Controllers\Admin\Settings\QuestionCategoryController;
use App\Http\Controllers\Admin\Settings\ReceiptSettingController;
use App\Http\Controllers\Admin\Settings\SubjectController;
use App\Http\Controllers\Admin\StaffUserController;
use App\Http\Controllers\Admin\SupportController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('admin.permission:dashboard,read')->name('dashboard');

    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->middlewareFor(['index', 'show'], 'admin.permission:students,read')
        ->middlewareFor(['create', 'edit', 'store', 'update'], 'admin.permission:students,write')
        ->middlewareFor(['destroy'], 'admin.permission:students,delete');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->middleware('admin.permission:students,write')->name('users.toggle');
    Route::post('/users/{user}/enrollments', [UserController::class, 'assignExam'])->middleware('admin.permission:students,write')->name('users.enrollments.store');
    Route::patch('/users/{user}/enrollments/{enrollment}/cancel', [UserController::class, 'cancelEnrollment'])->middleware('admin.permission:students,write')->name('users.enrollments.cancel');

    Route::resource('staff-users', StaffUserController::class)
        ->parameters(['staff-users' => 'staffUser'])
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor(['index'], 'admin.permission:staff_users,read')
        ->middlewareFor(['store', 'update'], 'admin.permission:staff_users,write')
        ->middlewareFor(['destroy'], 'admin.permission:staff_users,delete');
    Route::patch('/staff-users/{staffUser}/toggle', [StaffUserController::class, 'toggle'])->middleware('admin.permission:staff_users,write')->name('staff-users.toggle');

    Route::resource('roles', RoleController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor(['index'], 'admin.permission:roles,read')
        ->middlewareFor(['store', 'update'], 'admin.permission:roles,write')
        ->middlewareFor(['destroy'], 'admin.permission:roles,delete');

    Route::get('/schools/search', [SchoolController::class, 'search'])->middleware('admin.permission:schools,read')->name('schools.search');
    Route::get('/schools/export/excel', [SchoolController::class, 'excel'])->middleware('admin.permission:schools,read')->name('schools.excel');
    Route::resource('schools', SchoolController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor(['index'], 'admin.permission:schools,read')
        ->middlewareFor(['store', 'update'], 'admin.permission:schools,write')
        ->middlewareFor(['destroy'], 'admin.permission:schools,delete');
    Route::patch('/schools/{school}/toggle', [SchoolController::class, 'toggle'])->middleware('admin.permission:schools,write')->name('schools.toggle');

    Route::get('/data-entry', [DataEntryController::class, 'index'])->middleware('admin.permission:data_entry,read')->name('data-entry.index');
    Route::get('/data-entry/rows', [DataEntryController::class, 'rows'])->middleware('admin.permission:data_entry,read')->name('data-entry.rows');
    Route::patch('/data-entry/rows', [DataEntryController::class, 'updateRows'])->middleware('admin.permission:data_entry,write')->name('data-entry.rows.update');

    Route::resource('school-designations', SchoolDesignationController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middlewareFor(['index'], 'admin.permission:school_designations,read')
        ->middlewareFor(['store', 'update'], 'admin.permission:school_designations,write')
        ->middlewareFor(['destroy'], 'admin.permission:school_designations,delete');

    Route::resource('questions', QuestionController::class)
        ->middlewareFor(['index', 'show'], 'admin.permission:questions,read')
        ->middlewareFor(['create', 'edit', 'store', 'update'], 'admin.permission:questions,write')
        ->middlewareFor(['destroy'], 'admin.permission:questions,delete');
    Route::resource('exams', ExamController::class)
        ->middlewareFor(['index', 'show'], 'admin.permission:exams,read')
        ->middlewareFor(['create', 'edit', 'store', 'update'], 'admin.permission:exams,write')
        ->middlewareFor(['destroy'], 'admin.permission:exams,delete');
    Route::post('/exams/{exam}/duplicate', [ExamController::class, 'duplicate'])->middleware('admin.permission:exams,write')->name('exams.duplicate');
    Route::patch('/exams/{exam}/publish', [ExamController::class, 'publish'])->middleware('admin.permission:exams,write')->name('exams.publish');
    Route::patch('/exams/{exam}/unpublish', [ExamController::class, 'unpublish'])->middleware('admin.permission:exams,write')->name('exams.unpublish');
    Route::patch('/exams/{exam}/archive', [ExamController::class, 'archive'])->middleware('admin.permission:exams,write')->name('exams.archive');

    Route::get('/results', [ResultController::class, 'index'])->middleware('admin.permission:results,read')->name('results');
    Route::get('/results/{exam}', [ResultController::class, 'show'])->middleware('admin.permission:results,read')->name('results.show');
    Route::post('/results/{exam}/process', [ResultController::class, 'process'])->middleware('admin.permission:results,write')->name('results.process');
    Route::post('/results/{exam}/release', [ResultController::class, 'release'])->middleware('admin.permission:results,write')->name('results.release');
    Route::patch('/results/override/{result}', [ResultController::class, 'override'])->middleware('admin.permission:results,write')->name('results.override');

    Route::get('/certificates', [CertificateController::class, 'index'])->middleware('admin.permission:certificates,read')->name('certificates');
    Route::get('/certificates/{exam}', [CertificateController::class, 'show'])->middleware('admin.permission:certificates,read')->name('certificates.show');
    Route::post('/certificates/{exam}/upload', [CertificateController::class, 'upload'])->middleware('admin.permission:certificates,write')->name('certificates.upload');
    Route::post('/certificates/{exam}/generate', [CertificateController::class, 'generate'])->middleware('admin.permission:certificates,write')->name('certificates.generate');
    Route::delete('/certificates/{exam}/template', [CertificateController::class, 'deleteTemplate'])->middleware('admin.permission:certificates,delete')->name('certificates.template.delete');
    Route::get('/certificates/{exam}/download', [CertificateController::class, 'download'])->middleware('admin.permission:certificates,read')->name('certificates.download');

    Route::get('/payments', [PaymentController::class, 'index'])->middleware('admin.permission:payments,read')->name('payments');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware('admin.permission:payments,read')->name('payments.receipt');
    Route::patch('/payments/{payment}/reconcile', [PaymentController::class, 'reconcile'])->middleware('admin.permission:payments,write')->name('payments.reconcile');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('admin.permission:payments,write')->name('payments.refund');

    Route::get('/receipts', [ReceiptController::class, 'index'])->middleware('admin.permission:receipts,read')->name('receipts.index');
    Route::get('/receipts/bulk/download', [ReceiptController::class, 'bulk'])->middleware('admin.permission:receipts,read')->name('receipts.bulk');
    Route::get('/receipts/sales-report', [ReceiptController::class, 'salesReport'])->middleware('admin.permission:receipts,read')->name('receipts.sales-report');
    Route::get('/receipts/payments/{payment}/download', [ReceiptController::class, 'download'])->middleware('admin.permission:receipts,read')->name('receipts.download');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('admin.permission:reports,read')->name('reports.index');
    Route::get('/reports/export/excel', [ReportController::class, 'excel'])->middleware('admin.permission:reports,read')->name('reports.excel');
    Route::get('/reports/export/pdf', [ReportController::class, 'pdf'])->middleware('admin.permission:reports,read')->name('reports.pdf');

    Route::get('/coupons', [CouponController::class, 'index'])->middleware('admin.permission:coupons,read')->name('coupons');
    Route::post('/coupons', [CouponController::class, 'store'])->middleware('admin.permission:coupons,write')->name('coupons.store');
    Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->middleware('admin.permission:coupons,write')->name('coupons.update');
    Route::patch('/coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->middleware('admin.permission:coupons,write')->name('coupons.toggle');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('admin.permission:coupons,delete')->name('coupons.destroy');

    Route::get('/referrals', [ReferralController::class, 'index'])->middleware('admin.permission:referrals,read')->name('referrals');
    Route::get('/referrals/settings', [ReferralSettingController::class, 'index'])->middleware('admin.permission:referrals,read')->name('referrals.settings');
    Route::put('/referrals/settings', [ReferralSettingController::class, 'update'])->middleware('admin.permission:referrals,write')->name('referrals.settings.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('admin.permission:notifications,read')->name('notifications');
    Route::get('/notifications/students', [NotificationController::class, 'students'])->middleware('admin.permission:notifications,read')->name('notifications.students');
    Route::post('/notifications/preview', [NotificationController::class, 'preview'])->middleware('admin.permission:notifications,read')->name('notifications.preview');
    Route::post('/notifications/send', [NotificationController::class, 'send'])->middleware('admin.permission:notifications,write')->name('notifications.send');
    Route::delete('/notifications/{log}', [NotificationController::class, 'destroy'])->middleware('admin.permission:notifications,delete')->name('notifications.destroy');

    Route::get('/support', [SupportController::class, 'index'])->middleware('admin.permission:support,read')->name('support.index');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->middleware('admin.permission:support,read')->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->middleware('admin.permission:support,write')->name('support.reply');
    Route::patch('/support/{ticket}/status', [SupportController::class, 'updateStatus'])->middleware('admin.permission:support,write')->name('support.status');

    Route::get('/emails', [EmailTemplateController::class, 'index'])->middleware('admin.permission:emails,read')->name('emails');
    Route::put('/emails/{template}', [EmailTemplateController::class, 'update'])->middleware('admin.permission:emails,write')->name('emails.update');
    Route::patch('/emails/{template}/toggle', [EmailTemplateController::class, 'toggle'])->middleware('admin.permission:emails,write')->name('emails.toggle');
    Route::post('/emails/{template}/test', [EmailTemplateController::class, 'sendTest'])->middleware('admin.permission:emails,write')->name('emails.test');
    Route::get('/emails/{template}/preview', [EmailTemplateController::class, 'preview'])->middleware('admin.permission:emails,read')->name('emails.preview');

    Route::get('/forms', [LeadController::class, 'index'])->middleware('admin.permission:forms,read')->name('forms.index');
    Route::delete('/forms/{lead}', [LeadController::class, 'destroy'])->middleware('admin.permission:forms,delete')->name('forms.destroy');

    Route::get('/content', [ContentController::class, 'index'])->middleware('admin.permission:content,read')->name('content');
    Route::put('/content/homepage/{section}', [ContentController::class, 'update'])->middleware('admin.permission:content,write')->name('content.homepage.update');
    Route::post('/content/homepage/{section}/reset', [ContentController::class, 'reset'])->middleware('admin.permission:content,write')->name('content.homepage.reset');

    // ── Settings ────────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/subjects', [SubjectController::class, 'index'])->middleware('admin.permission:settings_subjects,read')->name('subjects');
        Route::post('/subjects', [SubjectController::class, 'store'])->middleware('admin.permission:settings_subjects,write')->name('subjects.store');
        Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->middleware('admin.permission:settings_subjects,write')->name('subjects.update');
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->middleware('admin.permission:settings_subjects,delete')->name('subjects.destroy');

        Route::get('/categories', [QuestionCategoryController::class, 'index'])->middleware('admin.permission:settings_categories,read')->name('categories');
        Route::post('/categories', [QuestionCategoryController::class, 'store'])->middleware('admin.permission:settings_categories,write')->name('categories.store');
        Route::put('/categories/{category}', [QuestionCategoryController::class, 'update'])->middleware('admin.permission:settings_categories,write')->name('categories.update');
        Route::delete('/categories/{category}', [QuestionCategoryController::class, 'destroy'])->middleware('admin.permission:settings_categories,delete')->name('categories.destroy');

        Route::get('/classes', [ClassLevelController::class, 'index'])->middleware('admin.permission:settings_classes,read')->name('classes');
        Route::post('/classes', [ClassLevelController::class, 'store'])->middleware('admin.permission:settings_classes,write')->name('classes.store');
        Route::put('/classes/{classLevel}', [ClassLevelController::class, 'update'])->middleware('admin.permission:settings_classes,write')->name('classes.update');
        Route::delete('/classes/{classLevel}', [ClassLevelController::class, 'destroy'])->middleware('admin.permission:settings_classes,delete')->name('classes.destroy');

        Route::get('/receipts', [ReceiptSettingController::class, 'index'])->middleware('admin.permission:settings_receipts,read')->name('receipts');
        Route::post('/receipts', [ReceiptSettingController::class, 'update'])->middleware('admin.permission:settings_receipts,write')->name('receipts.update');
    });
});

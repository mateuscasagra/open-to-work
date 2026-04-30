<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\AdminErrorLogsController;
use App\Http\Controllers\Api\Admin\AdminMetricsController;
use App\Http\Controllers\Api\ApplicationAttachmentController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\Location\LookupPostalCodeController;
use App\Http\Controllers\Api\Location\SupportedCountriesController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\OauthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\ResumePdfController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\SuggestionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

// Auth ------------------------------------------------------------------
Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth')
        ->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')
        ->name('auth.login');
    Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
        ->middleware('throttle:auth')
        ->name('auth.verify');
    Route::post('/resend-code', [AuthController::class, 'resendVerificationCode'])
        ->middleware('throttle:auth')
        ->name('auth.resend');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:auth')
        ->name('auth.forgot');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:auth')
        ->name('auth.reset');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // OAuth: /auth/{provider}/redirect -> /auth/{provider}/callback
    Route::get('/{provider}/redirect', [OauthController::class, 'redirect'])
        ->middleware('throttle:auth')
        ->whereIn('provider', ['google', 'linkedin', 'github'])
        ->name('auth.oauth.redirect');

    Route::get('/{provider}/callback', [OauthController::class, 'callback'])
        ->middleware('throttle:auth')
        ->whereIn('provider', ['google', 'linkedin', 'github'])
        ->name('auth.oauth.callback');
});

// Authenticated ----------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::singleton('profile', ProfileController::class)
        ->only(['show', 'update']);

    Route::get('/skills', [SkillController::class, 'index'])
        ->middleware('throttle:search')
        ->name('skills.index');

    Route::get('/jobs/matching', [JobController::class, 'matching'])->name('jobs.matching');
    Route::apiResource('jobs', JobController::class)
        ->only(['index', 'show']);

    Route::post('/resumes/pdf', [ResumePdfController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('resumes.pdf.store');
    Route::get('/resumes/{resume}/download', [ResumePdfController::class, 'download'])->name('resumes.pdf.download');
    Route::apiResource('resumes', ResumeController::class);

    Route::apiResource('applications', ApplicationController::class);
    Route::patch('/applications/{application}/status', [ApplicationController::class, 'changeStatus'])
        ->name('applications.status');
    Route::post('/applications/{application}/archive', [ApplicationController::class, 'archive'])
        ->name('applications.archive');
    Route::post('/applications/{application}/unarchive', [ApplicationController::class, 'unarchive'])
        ->name('applications.unarchive');

    Route::get('/metrics', MetricsController::class)->name('metrics.show');

    // Suggestions ------------------------------------------------------
    Route::get('/suggestions/quota', [SuggestionController::class, 'quota'])->name('suggestions.quota');
    Route::get('/suggestions', [SuggestionController::class, 'index'])->name('suggestions.index');
    Route::post('/suggestions', [SuggestionController::class, 'store'])
        ->middleware('throttle:suggestions-write')
        ->name('suggestions.store');
    Route::delete('/suggestions/{suggestion}', [SuggestionController::class, 'destroy'])->name('suggestions.destroy');
    Route::post('/suggestions/{suggestion}/vote', [SuggestionController::class, 'vote'])
        ->middleware('throttle:suggestions-vote')
        ->name('suggestions.vote');

    // Location ----------------------------------------------------------
    Route::prefix('location')->group(function (): void {
        Route::get('/countries', SupportedCountriesController::class)
            ->name('location.countries');
        Route::post('/lookup', LookupPostalCodeController::class)
            ->middleware('throttle:location-lookup')
            ->name('location.lookup');
    });

    // Admin -------------------------------------------------------------
    Route::middleware('admin')->prefix('admin')->group(function (): void {
        Route::get('/metrics', AdminMetricsController::class)->name('admin.metrics.show');
        Route::get('/error-logs', AdminErrorLogsController::class)->name('admin.error-logs.index');
    });

    // Attachments (spatie/medialibrary)
    Route::get('/applications/{application}/attachments', [ApplicationAttachmentController::class, 'index'])
        ->name('applications.attachments.index');
    Route::post('/applications/{application}/attachments', [ApplicationAttachmentController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('applications.attachments.store');
    Route::delete('/applications/{application}/attachments/{media}', [ApplicationAttachmentController::class, 'destroy'])
        ->name('applications.attachments.destroy');

    // LGPD — export/delete de conta (rate limit restrito)
    Route::middleware('throttle:account-sensitive')->group(function (): void {
        Route::get('/account/export', [AccountController::class, 'export'])->name('account.export');
        Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
    });
});

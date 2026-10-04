<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ProjectCommentController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\ProjectTrackingController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TrackerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::post('/account/setup', [SessionController::class, 'setup'])->middleware('throttle:5,1')->name('account.setup');
    Route::view('/login', 'users.demo-login')->name('login');
    Route::get('/account/login', [SessionController::class, 'create'])->name('account.login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.update');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});

Route::view('/', 'landing')->name('home');
Route::get('/dashboard', [ProjectOverviewController::class, 'dashboard'])->name('dashboard');
Route::post('/dashboard/comments/read', [ProjectOverviewController::class, 'readComments'])->middleware('throttle:120,1')->name('dashboard.comments.read');
Route::get('/dashboard/updates', [ProjectOverviewController::class, 'updates'])->name('dashboard.updates');
Route::get('/dashboard/projects/{reference}', [ProjectOverviewController::class, 'demo'])->name('dashboard.projects.show');
Route::get('/projects', [ProjectOverviewController::class, 'index'])->name('projects.index');
Route::get('/projects/{record}/overview', [ProjectOverviewController::class, 'show'])->whereNumber('record')->name('projects.overview');

Route::get('/projects/{record}/details', [ProjectTrackingController::class, 'details'])->whereNumber('record')->name('projects.details');
Route::get('/projects/{record}', [ProjectTrackingController::class, 'show'])->whereNumber('record')->name('projects.show');
Route::get('/projects/examples/{sample}/details', [ProjectTrackingController::class, 'sampleDetails'])->whereIn('sample', ['1', '2', '3'])->name('projects.sample-details');
Route::post('/projects/{record}/comments', [ProjectCommentController::class, 'store'])->whereNumber('record')->middleware(['auth', 'throttle:30,1'])->name('projects.comments.store');
Route::post('/projects/examples/{sample}/comments', [ProjectCommentController::class, 'sampleStore'])->whereIn('sample', ['1', '2', '3'])->middleware(['auth', 'throttle:30,1'])->name('projects.sample-comments.store');
Route::get('/projects/{record}/documents/{document}', [ProjectTrackingController::class, 'download'])->whereNumber('record')->whereNumber('document')->middleware('auth')->name('projects.documents.download');

foreach (array_keys(config('tracker')) as $module) {
    if (! in_array($module, ['subactivities', 'sectors', 'partners'])) {
        Route::get("{$module}/examples/{sample}", [$module === 'projects' ? ProjectTrackingController::class : TrackerController::class, 'sampleShow'])->whereIn('sample', $module === 'issues' && config('dashboard.presentation') ? array_map('strval', array_keys(config('dashboard.issue_samples'))) : ['1', '2', '3'])->name("{$module}.sample-show");
        Route::get("{$module}/examples/{sample}/edit", [TrackerController::class, 'sampleEdit'])->whereIn('sample', $module === 'issues' && config('dashboard.presentation') ? array_map('strval', array_keys(config('dashboard.issue_samples'))) : ['1', '2', '3'])->name("{$module}.sample-edit");
    }
    if ($module !== 'projects') {
        Route::get("{$module}/{record}/delete", [TrackerController::class, 'delete'])->name("{$module}.delete");
    }
    $routes = Route::resource($module, TrackerController::class)->parameters([$module => 'record']);
    if ($module === 'projects') {
        $routes->except(['destroy', 'show', 'index']);
    }
}

Route::get('/logs', [AuditLogController::class, 'index'])->name('logs.index');

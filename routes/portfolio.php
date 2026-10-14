<?php

use App\Http\Controllers\ComponentExtensionController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioRecordsController;
use App\Http\Controllers\ProjectOverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [ProjectOverviewController::class, 'dashboard'])->name('dashboard');
Route::get('/meetings/{id}/minutes', [PortfolioRecordsController::class, 'downloadMinutes'])->name('meetings.minutes');
Route::get('/financial-information/create', [PortfolioRecordsController::class, 'financialForm'])->name('financial-information.create');
Route::post('/financial-information', [PortfolioRecordsController::class, 'storeFinancialForm'])->name('financial-information.store');
Route::get('/financial-information/{id}/edit', [PortfolioRecordsController::class, 'financialForm'])->name('financial-information.edit');
Route::put('/financial-information', [PortfolioRecordsController::class, 'storeFinancialForm'])->name('financial-information.update');
Route::get('/dashboard/updates', [ProjectOverviewController::class, 'updates'])->name('dashboard.updates');
Route::post('/dashboard/comments/read', [ProjectOverviewController::class, 'readComments'])->name('dashboard.comments.read');
Route::get('/projects', [ProjectOverviewController::class, 'index'])->name('projects.index');
Route::get('/projects/create', [PortfolioController::class, 'create'])->middleware('auth')->name('projects.create');
Route::post('/projects', [PortfolioController::class, 'store'])->middleware('auth')->name('projects.store');
Route::post('/projects/{project}/archive', [PortfolioController::class, 'archive'])->name('projects.archive');
Route::get('/projects/{project}/edit', [PortfolioController::class, 'edit'])->middleware('auth')->name('projects.edit');
Route::put('/projects/{project}', [PortfolioController::class, 'update'])->middleware('auth')->name('projects.update');
Route::get('/projects/{project}', [PortfolioController::class, 'show'])->name('projects.show');
Route::get('/projects/{project}/overview', [PortfolioController::class, 'show'])->name('projects.overview');
Route::get('/projects/{project}/details', [PortfolioController::class, 'show'])->name('projects.details');
Route::post('/projects/{project}/comments', [PortfolioController::class, 'comment'])->middleware(['auth', 'throttle:30,1'])->name('projects.comments.store');
Route::post('/projects/{project}/documents', [PortfolioController::class, 'upload'])->middleware('auth')->name('projects.documents.store');
Route::get('/documents/{document}', [PortfolioController::class, 'download'])->middleware('auth')->name('documents.download');
Route::get('/extensions', [ComponentExtensionController::class, 'index'])->name('extensions.index');
Route::post('/extensions', [ComponentExtensionController::class, 'store'])->middleware('auth')->name('extensions.store');
Route::post('/extensions/{extension}/decision', [ComponentExtensionController::class, 'decide'])->middleware('auth')->name('extensions.decide');
Route::post('/extensions/{extension}/withdraw', [ComponentExtensionController::class, 'withdraw'])->middleware('auth')->name('extensions.withdraw');
foreach (array_keys(config('portfolio_records')) as $module) {
    if (isset(config('portfolio_records')[$module]['fields']['active'])) {
        Route::post('/'.$module.'/{id}/archive', [PortfolioRecordsController::class, 'archive'])->defaults('module', $module)->name($module.'.archive');
    }
    Route::get('/'.$module, [PortfolioRecordsController::class, 'index'])->defaults('module', $module)->name($module.'.index');
    Route::get('/'.$module.'/create', [PortfolioRecordsController::class, 'create'])->defaults('module', $module)->middleware('auth')->name($module.'.create');
    Route::post('/'.$module, [PortfolioRecordsController::class, 'store'])->defaults('module', $module)->middleware('auth')->name($module.'.store');
    Route::get('/'.$module.'/{id}/edit', [PortfolioRecordsController::class, 'edit'])->defaults('module', $module)->middleware('auth')->name($module.'.edit');
    Route::put('/'.$module.'/{id}', [PortfolioRecordsController::class, 'update'])->defaults('module', $module)->middleware('auth')->name($module.'.update');
    Route::get('/'.$module.'/{id}', [PortfolioRecordsController::class, 'show'])->defaults('module', $module)->name($module.'.show');
    Route::delete('/'.$module.'/{id}', [PortfolioRecordsController::class, 'destroy'])->defaults('module', $module)->middleware('auth')->name($module.'.destroy');
}
Route::get('/logs', [PortfolioController::class, 'logs'])->middleware('auth')->name('logs.index');

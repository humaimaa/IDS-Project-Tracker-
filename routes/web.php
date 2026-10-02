<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TrackerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::post('/account/setup', [SessionController::class, 'setup'])->middleware('throttle:5,1')->name('account.setup');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->middleware('throttle:10,1')->name('account.update');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/projects/{reference}', [DashboardController::class, 'show'])->name('dashboard.projects.show');

foreach (array_keys(config('tracker')) as $module) {
    if (! in_array($module, ['subactivities', 'sectors', 'partners'])) {
        Route::get("{$module}/examples/{sample}", [TrackerController::class, 'sampleShow'])->whereIn('sample', ['1', '2', '3'])->name("{$module}.sample-show");
        Route::get("{$module}/examples/{sample}/edit", [TrackerController::class, 'sampleEdit'])->whereIn('sample', ['1', '2', '3'])->name("{$module}.sample-edit");
    }
    if ($module !== 'projects') {
        Route::get("{$module}/{record}/delete", [TrackerController::class, 'delete'])->name("{$module}.delete");
    }
    $routes = Route::resource($module, TrackerController::class)->parameters([$module => 'record']);
    if ($module === 'projects') {
        $routes->except('destroy');
    }
}

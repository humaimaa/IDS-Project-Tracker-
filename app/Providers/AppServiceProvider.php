<?php

namespace App\Providers;

use App\DashboardUpdates;
use App\Models\TrackerRecord;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.header', DashboardUpdates::class);
        View::composer('layouts.app', function (BladeView $view): void {
            $user = auth()->user();
            $assignedUser = $user
                ? TrackerRecord::where('module', 'users')->where('data->email', $user->email)->first()
                : null;

            $view->with('sidebarRole', $assignedUser?->data['role'] ?? ($user ? 'No role assigned' : 'Section Officer'));
        });
    }
}

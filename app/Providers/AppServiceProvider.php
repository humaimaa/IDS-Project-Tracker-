<?php

namespace App\Providers;

use App\DashboardUpdates;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\ExtensionDecision;
use App\Models\ExtensionRequest;
use App\Models\Project;
use App\Models\ProjectPhaseHistory;
use App\Models\ProjectScheduleRevision;
use App\Models\TrackerRecord;
use App\Observers\PortfolioAuditObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        foreach (array_unique(array_merge(array_column(config('portfolio_records'), 'model'), [Project::class, Comment::class, ExtensionRequest::class, ExtensionDecision::class, Document::class, DocumentLink::class, ProjectScheduleRevision::class, ProjectPhaseHistory::class])) as $model) {
            $model::observe(PortfolioAuditObserver::class);
        }
        View::composer('partials.header', DashboardUpdates::class);
        View::composer('partials.sidebar', function (BladeView $view): void {
            $user = auth()->user();
            $count = config('portfolio.enabled') && $user?->hasPortfolioPermission('extensions.view') && $user->hasPortfolioPermission('extensions.approve')
                ? ExtensionRequest::where('reviewer_id', $user->id)->where('status', 'Pending')->count()
                : 0;
            $view->with('pendingExtensionCount', $count);
        });
        View::composer('layouts.app', function (BladeView $view): void {
            $user = auth()->user();
            if (config('portfolio.enabled')) {
                $view->with('sidebarRole', $user?->role?->name ?? 'Visitor');

                return;
            }
            $assignedUser = $user
                ? TrackerRecord::where('module', 'users')->where('data->email', $user->email)->first()
                : null;

            $view->with('sidebarRole', $assignedUser?->data['role'] ?? ($user ? 'No role assigned' : 'Section Officer'));
        });
    }
}

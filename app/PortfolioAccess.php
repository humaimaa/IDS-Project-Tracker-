<?php

namespace App;

use App\Models\Component;
use App\Models\FinancialReport;
use App\Models\Subcomponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class PortfolioAccess
{
    /** @param array<string, mixed> $parameters */
    public static function redirectToAllowed(string $route, array $parameters = []): RedirectResponse
    {
        if (auth()->user()?->hasPortfolioPermission(self::routePermission($route))) {
            return to_route($route, $parameters);
        }

        return to_route('account.edit');
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        $codes = ['dashboard.view', 'projects.view', 'projects.create', 'projects.update', 'projects.archive', 'projects.flagship', 'projects.assign', 'comments.view', 'comments.create', 'documents.view', 'documents.create', 'extensions.view', 'extensions.request', 'extensions.approve', 'extensions.withdraw', 'logs.view'];
        foreach (config('portfolio_records') as $module => $definition) {
            if (isset($definition['fields']['active'])) {
                $codes[] = $module.'.archive';
            }
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                if (($action === 'create' && ($definition['existing_only'] ?? false)) || ($action === 'update' && ($definition['append_only'] ?? false))) {
                    continue;
                }
                $codes[] = $module.'.'.$action;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function routePermission(string $name): string
    {
        return match ($name) {
            'meetings.minutes' => 'meetings.view',
            'financial-information.create', 'financial-information.store' => 'financial-reports.create',
            'financial-information.edit', 'financial-information.update' => 'financial-reports.update',
            'dashboard', 'dashboard.updates' => 'dashboard.view',
            'dashboard.comments.read' => 'comments.view',
            'projects.comments.store' => 'comments.create',
            'projects.documents.store' => 'documents.create',
            'documents.download' => 'documents.view',
            'extensions.store' => 'extensions.request',
            'extensions.decide' => 'extensions.approve',
            'extensions.withdraw' => 'extensions.withdraw',
            default => preg_replace('/\.(index|show|overview|details)$/', '.view', preg_replace('/\.edit$/', '.update', preg_replace('/\.store$/', '.create', preg_replace('/\.destroy$/', '.delete', $name)))),
        };
    }

    public static function scope(Builder $query): Builder
    {
        $user = auth()->user();
        if (! $user?->isProjectDirector()) {
            return $query;
        }
        $projects = $user->ownedProjects()->select('projects.id');

        return match ($query->getModel()->getTable()) {
            'projects' => $query->whereIn('id', $projects),
            'components', 'project_pipeline_steps', 'issues', 'comments', 'project_cost_versions', 'financing_agreements', 'financial_reports' => $query->whereIn('project_id', $projects),
            'subcomponents' => $query->whereIn('component_id', Component::whereIn('project_id', $projects)->select('id')),
            'progress_updates' => $query->where(fn ($q) => $q->whereIn('component_id', Component::whereIn('project_id', $projects)->select('id'))->orWhereIn('subcomponent_id', Subcomponent::whereIn('component_id', Component::whereIn('project_id', $projects)->select('id'))->select('id'))),
            'annual_financials', 'quarterly_financials' => $query->whereIn('report_id', FinancialReport::whereIn('project_id', $projects)->select('id')),
            'meetings' => $query->whereHas('projects', fn ($q) => $q->whereIn('projects.id', $projects)),
            default => $query,
        };
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\PortfolioAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePortfolio
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasPortfolioPermission(PortfolioAccess::routePermission($request->route()->getName())), 403);
        $project = $request->route('project');
        if ($project instanceof Project) {
            abort_unless($request->user()->canAccessProject($project->id), 403);
        }

        return $next($request);
    }
}

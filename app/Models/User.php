<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'pending_email_token_hash'])]
class User extends Authenticatable
{
    public function isProjectDirector(): bool
    {
        return $this->role?->name === 'Project Director';
    }

    public function isSuperAdmin(): bool
    {
        return $this->account_status === 'Active' && $this->role?->name === 'Super Admin';
    }

    public function canGrantRole(int $roleId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (Role::whereKey($roleId)->value('name') === 'Super Admin') {
            return false;
        }

        return ! DB::table('role_permissions')->where('role_id', $roleId)->whereNotIn('permission_id', DB::table('role_permissions')->where('role_id', $this->role_id)->select('permission_id'))->exists();
    }

    public function ownedProjects(): Builder
    {
        return Project::where(function ($query): void {
            $query->where('project_director_id', $this->id)->orWhereIn('id', DB::table('project_assignments')->where('user_id', $this->id)->where('assignment_role', 'Project Director')->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', today()->toDateString()))->select('project_id'));
        });
    }

    public function ownsProject(int $projectId): bool
    {
        return $this->ownedProjects()->whereKey($projectId)->exists();
    }

    public function canAccessProject(int $projectId): bool
    {
        return $this->account_status === 'Active' && (! $this->isProjectDirector() || $this->ownsProject($projectId));
    }

    public function canApproveExtension(int $projectId): bool
    {
        return $this->hasPortfolioPermission('extensions.approve') && ! $this->ownsProject($projectId) && $this->assignedToProject($projectId, true);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPortfolioPermission(string $permission): bool
    {
        return $this->account_status === 'Active' && DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_id', $this->role_id)->where('permissions.code', $permission)->exists();
    }

    public function assignedToProject(int $projectId, bool $approve = false): bool
    {
        return $this->account_status === 'Active' && DB::table('project_assignments')->where('project_id', $projectId)->where('user_id', $this->id)
            ->where(fn ($query) => $query->whereNull('active_to')->orWhere('active_to', '>=', today()->toDateString()))
            ->when($approve, fn ($query) => $query->where('can_approve_extension', true))->exists();
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_email_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

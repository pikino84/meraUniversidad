<?php

namespace App\Policies;

use App\Models\CourseReport;
use App\Models\User;

/**
 * Reportes de curso: super admin y admin (decisión del usuario, INSTRUCTIVO_REPORTES.md §10).
 * El link para compartir NO pasa por aquí: lo valida su token (CourseReportController::shared).
 */
class CourseReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(User::PANEL_ROLES);
    }

    public function view(User $user, CourseReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CourseReport $report): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, CourseReport $report): bool
    {
        return $this->viewAny($user);
    }
}

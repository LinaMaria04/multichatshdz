<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Solo superadmin y admin pueden ver la lista de usuarios
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Superadmin puede ver cualquier usuario
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin puede ver otros admins y usuarios normales, pero no superadmins
        if ($user->isAdmin()) {
            return !$model->isSuperAdmin();
        }

        // Usuario normal solo puede verse a sí mismo
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Solo superadmin y admin pueden crear usuarios
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Superadmin puede actualizar cualquier usuario
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin puede actualizar otros admins y usuarios normales, pero no superadmins
        if ($user->isAdmin()) {
            return !$model->isSuperAdmin();
        }

        // Usuario normal solo puede actualizarse a sí mismo
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Nadie puede eliminar su propia cuenta desde este panel
        if ($user->id === $model->id) {
            return false;
        }

        // Superadmin puede eliminar cualquier usuario excepto a sí mismo
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin puede eliminar otros admins y usuarios normales, pero no superadmins
        if ($user->isAdmin()) {
            return !$model->isSuperAdmin();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Similar a la lógica de delete
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return !$model->isSuperAdmin();
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Solo superadmin puede eliminar permanentemente
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can change the role of the model.
     */
    public function changeRole(User $user, User $model): bool
    {
        // Solo superadmin puede cambiar roles a superadmin
        if ($model->isSuperAdmin() || $user->role === 'superadmin') {
            return $user->isSuperAdmin();
        }

        // Admins pueden cambiar roles de usuarios normales a admin y viceversa
        if ($user->isAdmin()) {
            return !$model->isSuperAdmin();
        }

        return false;
    }
}
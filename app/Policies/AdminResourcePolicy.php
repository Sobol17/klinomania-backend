<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class AdminResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('access-admin');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('access-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('access-admin');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('access-admin');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('access-admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('access-admin');
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Model $record): bool
    {
        return false;
    }
}

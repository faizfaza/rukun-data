<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\WilayahAccess;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isAdminRt()) {
            return $auditLog->id_pengguna === $user->id;
        }

        if ($user->isAdminRw()) {
            if ($auditLog->id_pengguna === $user->id) {
                return true;
            }
            $targetUser = $auditLog->pengguna;
            if (! $targetUser) {
                return false;
            }
            $allowedWilayah = WilayahAccess::allowedWilayahIds($user) ?? [];

            return in_array($targetUser->id_wilayah, $allowedWilayah, true);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}


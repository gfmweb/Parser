<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->owns($user, $organization);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->owns($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->owns($user, $organization);
    }

    public function parse(User $user, Organization $organization): bool
    {
        return $this->owns($user, $organization);
    }

    private function owns(User $user, Organization $organization): bool
    {
        return $user->id === $organization->user_id;
    }
}

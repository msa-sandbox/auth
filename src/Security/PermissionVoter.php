<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// This class is registered automatically with the 'security.voter' tag since it extends Voter
class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, array_map(fn ($p) => $p->value, Permission::cases()), true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $permissions = [];
        foreach ($user->getRoles() as $role) {
            $permissions = [...$permissions, ...Roles::permissions($role)];
        }

        return in_array(Permission::from($attribute), $permissions, true);
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class AuthenticatedUserProvider implements AuthenticatedUserProviderInterface
{
    public function __construct(
        private Security $security,
        private AuthorizationCheckerInterface $authChecker,
    ) {
    }

    /**
     * @return User
     */
    public function getCurrentUser(): User
    {
        $user = $this->security->getUser();

        // This is theoretically impossible since we use auth for every request.
        if (!($user instanceof User)) {
            throw new LogicException('Actor not found');
        }

        return $user;
    }

    /**
     * @return int
     */
    public function getCurrentUserId(): int
    {
        return $this->getCurrentUser()->getId();
    }

    public function isGranted(string $role): bool
    {
        return $this->authChecker->isGranted($role);
    }

    public function isAdmin(): bool
    {
        return $this->isGranted(Roles::ROLE_ADMIN);
    }
}

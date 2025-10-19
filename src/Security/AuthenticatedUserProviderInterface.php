<?php

namespace App\Security;

use App\Entity\User;

/**
 * Of course, it is possible to get a user via Symfony\Bundle\SecurityBundle\Security->getUser()
 * directly within any service, bit it is not nice in terms of architecture.
 * There were 2 options:
 *  1. Provide user from the controller layer
 *  2. Wrap the Security package with a custom provider and use it.
 * I prefer the second option since sometimes there are already too many arguments passing from controllers.
 * In addition, I already use interfaces a lot, so it is consistent.
 */
interface AuthenticatedUserProviderInterface
{
    /**
     * @return User
     */
    public function getCurrentUser(): User;

    /**
     * @return int
     */
    public function getCurrentUserId(): int;

    /**
     * @param string $role
     *
     * @return bool
     */
    public function isGranted(string $role): bool;

    /**
     * @return bool
     */
    public function isAdmin(): bool;
}

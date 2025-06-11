<?php

namespace PassePlat\Core\User\Repository;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\User\Exception\UserRepositoryException;

/**
 * Base class for user repositories.
 */
abstract class UserRepository extends ComponentBasedObject
{
    /**
     * Authenticates the user.
     *
     * @param string $uid
     *   User ID.
     * @param string $token
     *   Authentication token.
     *
     * @return bool
     *   True if authenticated. false otherwise.
     */
    abstract public function authenticateUser($uid, $token): bool;

    /**
     * Tells if there are any users.
     *
     * @return bool
     *   True if any users, false otherwise.
     *
     * @throws UserRepositoryException
     */
    abstract public function hasUsers(): bool;
}

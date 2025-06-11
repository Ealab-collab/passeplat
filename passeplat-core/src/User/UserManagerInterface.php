<?php

namespace PassePlat\Core\User;

use PassePlat\Core\Exception\UserException;
use PassePlat\Core\Exception\UserRepositoryException;

/**
 * User manager.
 */
interface UserManagerInterface
{
    /**
     * Authenticates the user credentials.
     *
     * @param string $userId
     *   User ID.
     * @param string $token
     *   Token. Not the user password.
     *
     * @return bool
     *   True if credentials are ok, false otherwise.
     */
    public function authenticateUser(string $userId, string $token): bool;

    /**
     * Tells if there are any users.
     *
     * @return bool
     *   True if has any user. False otherwise.
     *
     * @throws UserRepositoryException
     */
    public function hasUsers(): bool;

    /**
     * Sets the repository type.
     *
     * @param string $repositoryType
     *   One of REPOSITORY_TYPE_* consts.
     *
     * @throws UserException
     */
    public function setRepositoryType(string $repositoryType): void;
}

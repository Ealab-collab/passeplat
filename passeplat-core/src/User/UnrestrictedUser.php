<?php

namespace PassePlat\Core\User;

/**
 * A defaut user type which does not have any restriction. Used when no user has been created.
 *
 * Should never be used when authentication is enabled.
 */
class UnrestrictedUser implements UserInterface
{
    /**
     * Gets the user ID.
     *
     * @return string
     *   The user ID.
     */
    public function getId(): string
    {
        return 'default';
    }
}

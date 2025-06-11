<?php

namespace PassePlat\Core\User;

/**
 * Interface for users in a general meaning (e.g. could be individuals or groups).
 */
interface UserInterface
{
    /**
     * Gets the user ID.
     *
     * @return string
     *   The user ID.
     */
    public function getId(): string;
}

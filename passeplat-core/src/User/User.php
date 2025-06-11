<?php

namespace PassePlat\Core\User;

/**
 * User definition.
 */
class User implements UserInterface
{
    /**
     * User ID.
     *
     * @var string
     */
    private $id;

    /**
     * User constructor.
     *
     * @param string $id
     *   Passeplat user ID.
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}

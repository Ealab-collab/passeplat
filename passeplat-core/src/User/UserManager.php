<?php

namespace PassePlat\Core\User;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\Exception\UserException;
use PassePlat\Core\User\Repository\LocalConfigUserRepository;
use PassePlat\Core\User\Repository\UserRepository;
use PassePlat\User\Exception\UserRepositoryException;

/**
 * Manages users.
 */
class UserManager extends ComponentBasedObject implements UserManagerInterface
{
    const REPOSITORY_TYPE__LOCAL_CONFIG = 'REPOSITORY_TYPE__LOCAL_CONFIG';
    const REPOSITORY_TYPE__NONE = 'REPOSITORY_TYPE__NONE';

    /**
     * Current repository type.
     *
     * @var string
     */
    protected $currentRepositoryType = self::REPOSITORY_TYPE__NONE;

    /**
     * The user repository which holds all definitions.
     *
     * @var UserRepository
     */
    protected $userRepository;

    public function authenticateUser(string $userId, string $token): bool
    {
        if (empty($this->userRepository)) {
            // Cannot authenticate on empty user repository.
            return false;
        }

        // Authenticate against user base.
        return $this->userRepository->authenticateUser($userId, $token);
    }

    /**
     * Allowed repository types.
     *
     * @return string[]
     *   Array of allowed repository types.
     */
    protected function getAllowedRepositoryTypes()
    {
        return [
            LocalConfigUserRepository::class => static::REPOSITORY_TYPE__LOCAL_CONFIG,
        ];
    }

    public function hasUsers(): bool
    {
        return $this->userRepository->hasUsers();
    }

    public function setRepositoryType(string $repositoryType): void
    {
        $repositoryClass = '';

        foreach ($this->getAllowedRepositoryTypes() as $className => $type) {
            if ($repositoryType === $type) {
                $repositoryClass = $className;
                break;
            }
        }

        if (empty($repositoryClass)) {
            throw new UserRepositoryException(
                'Invalid user repository type.'
            );
        }

        if ($this->currentRepositoryType === $repositoryType) {
            // Already loaded.
            return;
        }

        // Remove current repository (if any).
        $this->removeComponentsByClassName(UserRepository::class);

        try {
            // Append new repository. This is expected to work without failure.
            $this->userRepository = $this->addComponentByClassName($repositoryClass);
        } catch (UnmetDependencyException $e) {
            throw new UserException('Could not load user repository component.', $e);
        }

        // Changed current repository type.
        $this->currentRepositoryType = $repositoryType;
    }
}

<?php

namespace PassePlat\Core\WebService;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerManager;
use PassePlat\Core\User\UserInterface;
use Psr\Http\Message\UriInterface;

/**
 * Holds the definition of a web service.
 */
class WebService extends ComponentBasedObject implements WebServiceInterface
{
    /**
     * Active URL of this webservice.
     *
     * @var UriInterface
     */
    private $url;

    /**
     * The user which accessed this web service in the current session.
     *
     * @var UserInterface
     */
    private $accessingUser;

    /**
     * Task manager for this web service.
     *
     * @var TaskHandlerManager
     */
    private $taskManager;

    /**
     * Tasks configuration.
     *
     * @var array
     */
    private $tasks;

    /**
     * Users allowed to use this webservice.
     *
     * @var UserInterface[]
     */
    private $users;

    /**
     * Service ID.
     *
     * @var string
     */
    private $webServiceId;

    public function executeTasksForEvent(string $eventName, AnalyzableContent $analyzableContent): void
    {
        $this->taskManager->executeTasks(
            $analyzableContent,
            $this->getTasksConfiguration($eventName),
            $eventName
        );
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(TaskHandlerManager::class, $this->taskManager);
        return $definitions;
    }

    public function getAccessingUser(): UserInterface
    {
        return $this->accessingUser;
    }

    public function getDestinationUrl(): string
    {
        return $this->url->__toString();
    }

    public function getTasksConfiguration(string $eventName = ''): array
    {
        if (empty($eventName)) {
            return $this->tasks;
        }

        if (empty($this->tasks[$eventName])) {
            return [];
        }

        return $this->tasks[$eventName];
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    public function getWebServiceId(): string
    {
        return $this->webServiceId;
    }

    public function initValues(
        string $webServiceId,
        array $users,
        UriInterface $webServiceUri,
        ?UserInterface $accessingUser,
        array $tasks = []
    ): void {
        $this->webServiceId = $webServiceId;
        $this->users = $users;
        $this->url = $webServiceUri;
        $this->accessingUser = $accessingUser;
        $this->tasks = $tasks;
    }
}

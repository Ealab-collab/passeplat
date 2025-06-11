<?php

namespace PassePlat\Core\WebService;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\User\UserInterface;
use Psr\Http\Message\UriInterface;

/**
 * Interface defining a web service.
 */
interface WebServiceInterface
{
    const PHASE__EMITTED_RESPONSE = 'emittedResponse';
    const PHASE__DESTINATION_REACH_FAILURE = 'destinationReachFailure';
    const PHASE__DESTINATION_REQUEST_PREPARATION = 'destinationRequestPreparation';
    const PHASE__STARTED_RECEIVING = 'startedReceiving';

    /**
     * Gets the user who accessed this web service in the current session.
     *
     * May be empty if the web service is not currently accessed.
     *
     * @return UserInterface
     */
    public function getAccessingUser(): UserInterface;

    /**
     * Gets the destination URL string.
     *
     * @return string
     *   The destination URI string.
     */
    public function getDestinationUrl(): string;

    /**
     * Gets the configuration for preprocessors enabled on this web service.
     *
     * @param string $eventName
     *   An optional event name to retrieve a part of the configuration.
     *   Leaving it empty returns all the configuration.
     *
     * @return array
     *   Array of tasks configuration.
     */
    public function getTasksConfiguration(string $eventName = ''): array;

    /**
     * Gets the users which are allowed on this web service.
     *
     * @return UserInterface[]
     *   Array of UserInterface. May contain a unique value, or nil (orphaned web services).
     */
    public function getUsers(): array;

    /**
     * Gets the web service ID.
     *
     * @return string
     *   ID as string.
     */
    public function getWebServiceId(): string;

    /**
     * Inits common values defining a WebService instance.
     * @param string $webServiceId
     *   The web service ID.
     * @param UserInterface[] $users
     *   Users on this web service.
     * @param UriInterface $webServiceUri
     *   The active URL of this webservice.
     * @param UserInterface $accessingUser
     *   The user which accessed this web service in the current session.
     */
    public function initValues(
        string $webServiceId,
        array $users,
        UriInterface $webServiceUri,
        ?UserInterface $accessingUser
    ): void;

    /**
     * Executes tasks for the given event.
     *
     * @param string $eventName
     *   Event name. See EVENT__ consts.
     * @param AnalyzableContent $analyzableContent
     *   Object containing the necessary values for the event.
     */
    public function executeTasksForEvent(string $eventName, AnalyzableContent $analyzableContent): void;
}

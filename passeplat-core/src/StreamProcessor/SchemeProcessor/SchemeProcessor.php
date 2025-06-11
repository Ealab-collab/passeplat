<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\EventInterface;
use Dakwamine\Component\Event\EventListenerInterface;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Exception\BadRequestException;
use PassePlat\Core\Exception\HttpException;
use PassePlat\Core\Exception\UserNotFoundException;
use PassePlat\Core\Exception\WebServiceInvalidUriException;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Event\ProcessSchemeEvent;
use PassePlat\Core\WebService\WebServiceInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Stream processor for a given scheme.
 *
 * Scheme processors do not handle authentication on passeplat users and web services. This is done by the stream
 * processor only.
 */
abstract class SchemeProcessor extends ComponentBasedObject implements EventListenerInterface
{
    /**
     * Gets the schemes handled by this processor.
     *
     * @return array
     */
    abstract protected function getHandledSchemes(): array;

    public function handleEvent(EventInterface $event): void
    {
        switch ($event->getName()) {
            case ProcessSchemeEvent::EVENT_NAME:
                /** @var ProcessSchemeEvent $event */
                if (in_array($event->getScheme(), $this->getHandledSchemes())) {
                    $event->addProcessor($this);
                }
                break;
        }
    }

    /**
     * Processes the request.
     *
     * @param ServerRequestInterface $request
     *   The request to process.
     * @param AnalyzableContent $analyzableContent
     *   Main object containing data for analysis.
     * @param string $destinationUrl
     *   The destination URL.
     * @param WebServiceInterface $webService
     *   Web service object bound to this request.
     *
     * @throws BadRequestException
     *   The request PassePlat received did not satisfy all conditions to be processed (missing params, auth, etc).
     * @throws HttpException
     *   Exceptions thrown on HTTP errors (e.g.: destination server unreachable).
     * @throws UserNotFoundException
     *   The user authentication failed.
     * @throws WebServiceInvalidUriException
     *   No valid URI found for contacting the destination service.
     * @throws \Exception
     *   All unmanageable errors.
     */
    abstract public function processRequest(
        ServerRequestInterface $request,
        AnalyzableContent $analyzableContent,
        $destinationUrl,
        WebServiceInterface $webService
    ): void;
}

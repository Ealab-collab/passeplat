<?php

namespace PassePlat\Core\StreamProcessor;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\EventDispatcher;
use Dakwamine\Component\RootDependencyDefinition;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Uri;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\WebService;
use PassePlat\Core\Exception\BadRequestException;
use PassePlat\Core\Exception\HttpException;
use PassePlat\Core\Exception\UserNotFoundException;
use PassePlat\Core\Exception\WebServiceInvalidUriException;
use PassePlat\Core\PassePlatResponseFactory;
use PassePlat\Core\PassePlatResponseFactoryInterface;
use PassePlat\Core\Security\HostChecker;
use PassePlat\Core\Security\HostCheckerInterface;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Event\ProcessSchemeEvent;
use PassePlat\Core\WebService\WebServiceManager;
use PassePlat\Core\WebService\WebServiceManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Entry point for stream processing.
 */
class StreamProcessor extends ComponentBasedObject implements StreamProcessorInterface
{
    /**
     * Event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Host checker.
     *
     * @var HostCheckerInterface
     */
    private $hostChecker;

    /**
     * PassePlat response factory.
     *
     * @var PassePlatResponseFactoryInterface
     */
    private $passePlatResponseFactory;

    /**
     * Web service manager.
     *
     * @var WebServiceManagerInterface
     */
    private $webServiceManager;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(EventDispatcher::class, $this->eventDispatcher);
        $definitions[] = new RootDependencyDefinition(HostChecker::class, $this->hostChecker);
        $definitions[] = new RootDependencyDefinition(PassePlatResponseFactory::class, $this->passePlatResponseFactory);
        $definitions[] = new RootDependencyDefinition(WebServiceManager::class, $this->webServiceManager);
        return $definitions;
    }

    public function processRequest(ServerRequestInterface $request, AnalyzableContent $analyzableContent): void
    {
        // The webservice may be an unnamed one, especially when the service id is wrong or non-existent.
        // This is for system resilience: if the service is wrongly configured, the stream processor still does its
        // primary job of forwarding requests.
        $webService = $this->webServiceManager->getWebServiceFromRequest($request);

        /** @var WebService $webServiceComponent */
        $webServiceComponent = $analyzableContent->getComponentByClassName(WebService::class, true);
        $webServiceComponent->setWebService($webService);

        // Get the destination URL from the query params.
        $destinationUrl = $webService->getDestinationUrl();

        /** @var RequestInfo $requestInfoComponent */
        $requestInfoComponent = $analyzableContent->getComponentByClassName(RequestInfo::class, true);
        $requestInfoComponent->setDestinationUrl($destinationUrl);
        $requestInfoComponent->setRequest($request);

        // Determine the URI scheme to find the matching scheme processor.
        $uri = new Uri($destinationUrl);

        $scheme = $uri->getScheme();

        $event = new ProcessSchemeEvent($scheme);
        $this->eventDispatcher->dispatch($event);

        $schemeProcessors = $event->getProcessors();

        if (!empty($schemeProcessors)) {
            foreach ($schemeProcessors as $processor) {
                $processor->processRequest($request, $analyzableContent, $destinationUrl, $webService);
            }
        }
    }

    /**
     * Processes a request.
     *
     * @param AnalyzableContent $analyzableContent
     *   Contains request analysis.
     *
     * @throws UserNotFoundException
     * @throws WebServiceInvalidUriException
     * @throws BadRequestException
     * @throws HttpException
     */
    public function processRequestFromGlobals(AnalyzableContent $analyzableContent): void
    {
        $request = ServerRequest::fromGlobals();
        $this->processRequest($request, $analyzableContent);
    }
}

<?php

namespace PassePlat\App;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\ListenerProvider;
use Dakwamine\Component\Event\ListenerProviderInterface;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\App\Exception\ErrorCode;
use PassePlat\App\Exception\ErrorString;
use PassePlat\App\Psr7\ApplicationMessageJsonResponse;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Config\Configuration;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\Core\Exception\HttpHeaderException;
use PassePlat\Core\PassePlatResponseFactory;
use PassePlat\Core\StreamProcessor\StreamProcessor;
use Teapot\StatusCode;

/**
 * Main entry point for passeplat app.
 *
 * TODO: move to core?
 */
class PassePlat extends ComponentBasedObject
{
    /**
     * The analyzable content object.
     *
     * @var AnalyzableContent
     */
    private $analyzableContent;

    /**
     * Configuration holder.
     *
     * @var Configuration
     */
    private $configuration;

    /**
     * Listener provider.
     *
     * @var ListenerProviderInterface
     */
    private $listenerProvider;

    /**
     * Passe plat response factory.
     *
     * @var PassePlatResponseFactory
     */
    private $passePlatResponseFactory;

    /**
     * Stream processor.
     *
     * @var StreamProcessor
     */
    private $streamProcessor;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(AnalyzableContent::class, $this->analyzableContent);
        $definitions[] = new RootDependencyDefinition(Configuration::class, $this->configuration);
        $definitions[] = new RootDependencyDefinition(ListenerProvider::class, $this->listenerProvider);
        $definitions[] = new RootDependencyDefinition(PassePlatResponseFactory::class, $this->passePlatResponseFactory);
        $definitions[] = new RootDependencyDefinition(StreamProcessor::class, $this->streamProcessor);
        return $definitions;
    }

    /**
     * Loads the main configuration.
     *
     * @throws ConfigException
     */
    public function loadConfiguration(): void
    {
        $this->configuration->initializeConfiguration(__DIR__ . '/../config/emerya');
    }

    /**
     * Starts the main stuff.
     */
    public function processStream(): void
    {
        // TODO: ne pas faire echo des messages d'erreur, mais les loguer quelque part pour ne pas altérer le contenu
        // des réponses.
        try {
            // Process the request.
            $this->streamProcessor->processRequestFromGlobals($this->analyzableContent);
        } catch (\PassePlat\Core\Exception\Exception $e) {
            // Generic PassePlat exception.
            $errorChain = $e->getPpCodeChain();
            $errorChain[] = ErrorCode::PASSEPLAT;

            $response = new ApplicationMessageJsonResponse(
                StatusCode::INTERNAL_SERVER_ERROR,
                [],
                ErrorString::buildCriticalError($errorChain)
            );

            try {
                // Do not attempt to analyze or anything;
                // This is a serious PassePlat error which should not be logged, etc.
                $this->passePlatResponseFactory->emitResponse($response);
            } catch (HttpHeaderException $e) {
                // This means that the response was badly built. How could this happen?
                $errorChain[] = ErrorCode::MALFORMED_MESSAGE;
                echo ErrorString::buildUnknownError($errorChain);
            } catch (\Exception $e) {
                // Generic exception handling for unknown errors.
                // When having this error, it means we could handle the error more precisely.
                echo ErrorString::buildUnknownError([ErrorCode::UNKNOWN]);
            }
        } catch (\Exception $e) {
            // Generic exception handling for unknown errors.
            // When having this error, it means we could handle the error more precisely.
            echo ErrorString::buildUnknownError([ErrorCode::UNKNOWN]);
        }
    }

    /**
     * Registers event listeners.
     *
     * @param EventListenerDefinition[] $eventListenerDefinitions
     *   Definitions to register.
     */
    public function registerEventListeners(array $eventListenerDefinitions): void
    {
        foreach ($eventListenerDefinitions as $listenerDefinition) {
            $this->listenerProvider->addListener(
                $listenerDefinition->eventName,
                $listenerDefinition->listenerClassName,
                !empty($listenerDefinition->priority) ? $listenerDefinition->priority : 0
            );
        }
    }
}

<?php

namespace PassePlat\Logger\Elastic\StreamProcessor\SchemeProcessor\Task;

use Dakwamine\Component\Event\EventInterface;
use Dakwamine\Component\Event\EventListenerInterface;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Config\Event\GetEnabledConfigItemEvent;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;
use PassePlat\Core\Tool\DateTime;
use PassePlat\Logger\Elastic\Config\ConfigItem\Elasticsearch;
use PassePlat\Logger\Elastic\Definitions\AnalyzableContentIndexDef;
use PassePlat\Logger\Elastic\Definitions\ErrorsIndexDef;
use PassePlat\Logger\Elastic\Exception\CouldNotCreateElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\CouldNotLoadElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;

/**
 * Logger for Elasticsearch.
 *
 * This task handles the logging of analyzable content to Elasticsearch,
 * including error handling and schema validation.
 */
class ElasticsearchLogger_0 extends TaskHandlerBase implements EventListenerInterface
{
    /**
     * Date time tool.
     *
     * @var DateTime
     */
    private $dateTime;

    /**
     * Service that provides methods to interact with Elasticsearch.
     *
     * @var ElasticsearchService
     */
    protected ElasticsearchService $elasticSearchService;

    /**
     * Executes the task.
     *
     * @param AnalyzableContent $analyzableContent
     *   The object containing the values which may be read/edited.
     * @param array $options
     *   Array of options for this task.
     * @param string $eventName
     *   The name of the triggered event.
     *
     * @throws UnmetDependencyException
     */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        $elasticSearchService = $this->initializeElasticsearchService();

        if (empty($elasticSearchService)) {
            return;
        }

        $contentToLog = $analyzableContent->getDataToLog();
        $errors = $this->retrieveAndCleanErrors($contentToLog);

        try {
            $response = $this->logContent($contentToLog);

            if ($this->isResponseValid($response)) {
                $transactionId = $response['_id'];
                $this->logErrors($errors, $transactionId);
            }
        } catch (ElasticsearchException $exception) {
            // Elasticsearch serious failure (connectivity issues, server down, etc.).
            // Do not break the remaining of processing for resilience.
        }
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(DateTime::class, $this->dateTime);
        return $definitions;
    }

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                'type' => 'div',
                'attributes' => [
                    'class' => '',
                ],
                'content' => 'This task version has no configurable options.'
            ],
            'listForms' => [],
        ];
    }

    public function handleEvent(EventInterface $event): void
    {
        switch ($event->getName()) {
            case GetEnabledConfigItemEvent::EVENT_NAME:
                try {
                    /** @var GetEnabledConfigItemEvent $event */
                    $event->registerConfigItem(Elasticsearch::class);
                } catch (UnmetDependencyException $e) {
                    // Do not break it here.
                }
                break;
        }
    }

    /**
     * Initializes the Elasticsearch service.
     *
     * @return ElasticsearchService|null
     *
     * @throws UnmetDependencyException
     */
    protected function initializeElasticsearchService(): ?ElasticsearchService
    {
        /** @var ElasticsearchService $elasticSearchService */
        $elasticSearchService = $this->getComponentByClassName(ElasticsearchService::class, true);

        if ($elasticSearchService) {
            $this->elasticSearchService = $elasticSearchService;
        }

        return $elasticSearchService;
    }

    /**
     * Checks if the response from Elasticsearch is valid.
     *
     * @param array $response
     *   The response from Elasticsearch.
     *
     * @return bool
     *   True if the response is valid, false otherwise.
     */
    private function isResponseValid(array $response): bool
    {
        return !empty($response) && ($response['result'] === 'created');
    }

    /**
     * Logs content to Elasticsearch.
     *
     * @param array $contentToLog
     *   The content to log.
     *
     * @return array
     *   The response from Elasticsearch.
     *
     * @throws ElasticsearchException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    private function logContent(array $contentToLog): array
    {
        $indexDef = new AnalyzableContentIndexDef();

        return $this->elasticSearchService->logItem(
            $indexDef,
            $contentToLog,
        );
    }

    /**
     * Logs errors to Elasticsearch.
     *
     * @param array $errors
     *   The errors to log.
     * @param string $transactionId
     *   The transaction ID associated with the log entry.
     *
     * @throws CouldNotCreateElasticsearchIndexException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    private function logErrors(array $errors, string $transactionId): void
    {
        if (!empty($errors)) {
            foreach ($errors as &$error) {
                $error['transaction_id'] = $transactionId;
            }

            $this->elasticSearchService->logBulk(new ErrorsIndexDef(), $errors);
        }
    }

    /**
     * Retrieves and cleans errors from the content.
     *
     * @param array $content
     *   The content from which to retrieve and clean errors.
     *
     * @return array
     *   The list of errors.
     */
    private function retrieveAndCleanErrors(array &$content): array
    {
        $errors = $content['errors'] ?? [];
        unset($content['errors']);

        return $errors;
    }
}

<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderField;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\Exception\Exception;
use PassePlat\Forms\Exception\TaskException;

/**
 * Task handlers are used during streams events.
 */
abstract class TaskHandlerBase extends ComponentBasedObject
{
    /**
     * Executes the task.
     *
     * @param AnalyzableContent $analyzableContent
     *   The object containing the values which may be read/edited.
     * @param array $options
     *   Array of options for this task.
     * @param string $eventName
     *   The name of the triggered event.
     */
    abstract public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void;

    /**
     * Gets the content type from the headers array of the stream.
     *
     * @param array $headers
     *   The array headers.
     *
     * @return string|null
     *   The content type if found.
     */
    protected function getContentTypeFromHeader(array $headers)
    {
        /** @var string|null $contentType */
        $contentType = null;

        foreach ($headers as $name => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (strtolower($name) !== 'content-type') {
                    continue;
                }

                if (empty($contentType)) {
                    $contentType = $value;
                    continue;
                }

                if ($contentType !== $value) {
                    // The content-type header is duplicated AND the values are not the same for each of them.
                    return null;
                }
            }
        }

        return $contentType;
    }

    /**
     * Get an array corresponding to a 'data.json' to construct a form using jsonForms.io.
     *
     * If the method does not need to be overridden,
     * it solicits the method of the task from the immediately preceding version.
     *
     * @param array|null $providedData
     *   The provided data replaces the default values typically assigned by the web service.
     * @return array
     *   An array corresponding to a 'data.json'.
     *
     * @throws TaskException
     *   If the method is not overridden in version zero of the task.
     */
    public static function getFormData(?array $providedData = null): array
    {
        /** @var TaskHandlerBase $previousTask */
        $previousTask = static::getPreviousTask();

        return $previousTask::getFormData($providedData);
    }

    /**
     * Returns an array containing the 'renderView' and 'listForms' used by jsonToReact for rendering the task form.
     *
     * If the method does not need to be overridden,
     * it solicits the method of the task from the immediately preceding version.
     *
     * @param string $rootPath
     *   The root path that defines the data path scope used by the task form.
     *
     * @return array
     *   An array containing two keys: 'renderView' and 'listForms'.
     *
     * @throws TaskException
     *   If the method is not overridden in version zero of the task.
     */
    public static function getFormDefinition(string $rootPath = '~'): array
    {
        /** @var TaskHandlerBase $previousTask */
        $previousTask = static::getPreviousTask();

        return $previousTask::getFormDefinition($rootPath);
    }

    /**
     * Gets the full classname of the previous task.
     *
     * @return string
     *   The full classname of the previous task.
     *
     * @throws TaskException
     *   If any method necessary for constructing forms is not implemented in version zero of the task.
     */
    public static function getPreviousTask(): string
    {
        $class = static::class;

        [$className, $classVersion] = explode('_', $class, 2);

        if ($classVersion === '0') {
            throw new TaskException(
                "The methods for obtaining jsonForms are required in version 0 of $className."
            );
        }

        return $className . '_' . ($classVersion -1);
    }

    /**
     * Checks whether the task has a form or not.
     *
     * By default, each task has a form.
     *
     * @return bool
     */
    public static function hasEnableForm(): bool
    {
        return true;
    }

    /**
     * Tells if the given ResponseInfo transfer encoding is chunked.
     *
     * @param ResponseInfo $responseInfo
     *   ResponseInfo component.
     *
     * @return bool
     *   True is transfer encoding is chunked.
     */
    protected function isChunkedTransferEncoding(ResponseInfo $responseInfo): bool
    {
        try {
            /** @var Header $headerList */
            $headerList = $responseInfo->getComponentByClassName(Header::class);

            /** @var HeaderField[] $headers */
            $headers = $headerList->getComponentsByClassName(HeaderField::class);

            foreach ($headers as $header) {
                if (strtolower($header->getName()) === 'transfer-encoding' && $header->getValue() === 'chunked') {
                    return true;
                }
            }
        } catch (UnmetDependencyException $e) {
            return false;
        }

        return false;
    }

    /**
     * Checks if the content type from the header of the stream contains the given list.
     *
     * @param array $headers
     *   Headers, in a multidimensional structure [key => [value1, value2]].
     * @param array $expectedContentTypes
     *   Array of expected content types.
     *
     * @return bool
     *   True if the stream is one of the valid given content types.
     */
    protected function isOfAllowedContentType(array $headers, array $expectedContentTypes): bool
    {
        $contentType = $this->getContentTypeFromHeader($headers);

        if (empty($contentType)) {
            return false;
        }

        foreach ($expectedContentTypes as $expectedContentType) {
            // Content types are case insensitive.
            if (strtolower($expectedContentType) === strtolower($contentType)) {
                return true;
            }
        }

        // None of the expected content types matched the declared content type in header.
        return false;
    }

    /**
     * Registers a task handler into the TaskHandlerManager's registry.
     *
     * @throws Exception
     *   If TaskHandlerManager fails to instantiate.
     * @throws UnmetDependencyException
     */
    public static function register(): void
    {
        /** @var TaskHandlerManager $taskHandlerManager */
        $taskHandlerManager = static::getRootComponentByClassName(TaskHandlerManager::class, true);

        if (empty($taskHandlerManager)) {
            throw new Exception('Unable to instantiate TaskHandlerManager.');
        }

        $taskHandlerManager->registerTaskHandler(static::class);
    }

    /**
     * Replace the default data of a jsonForms task with the provided data only for existing keys.
     *
     * @param array $defaultData
     *   The default data array.
     * @param array|null $providedData
     *   The provided data array.
     *
     * @return array
     *   The merged data array.
     */
    public static function replaceFormData(array $defaultData, ?array $providedData = null): array
    {
        $mergedData = $defaultData;

        if (!empty($providedData)) {
            // All the task options are within the 'options' key of the $providedData,
            // unlike the conditions, which include 'invertResults,' etc.
            foreach ($providedData as $key => $value) {
                if ($key === 'options' && is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (isset($defaultData['options'][$k])) {
                            $mergedData['options'][$k] = $v;
                        }
                    }
                }
            }
        }

        return $mergedData;
    }
}

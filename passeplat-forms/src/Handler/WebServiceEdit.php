<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Exception\BadRequestException;
use PassePlat\Forms\Exception\ConditionException;
use PassePlat\Forms\Exception\TaskException;
use PassePlat\Forms\Exception\WebServiceException;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerManager;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Yaml\Yaml;

class WebServiceEdit extends Handler
{
    private const SERVER_YAML_FILE = './../passeplat-forms/blueprint/edit.yaml';
    private const WEBSERVICES_DIRECTORY = '../config/app/webservice/';
    private const CONDITIONS_DIRECTORY = '../passeplat-core/src/StreamProcessor/SchemeProcessor/Task/Condition';
    private const CONDITIONS_NAMESPACE = 'PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition\\';

    private array $webservices = [];
    private array $mostRecentConditions = [];
    private array $mostRecentTasks = [];

    /**
     * Escapes the class name by adding a leading backslash
     *
     * @param string $className
     *   The class name to escape
     *
     * @return string
     *   The escaped class name.
     */
    private function escapeClassName(string $className): string
    {
        return '\\' . $className;
    }

    /**
     * Extracts entity name and version from a fully qualified class name.
     *
     * @param $qualifiedEntityClass
     *   The fully qualified entity class name.
     *
     * @return false|string[]
     *   An array containing the entity name and version.
     */
    private function extractEntityAndVersion($qualifiedEntityClass)
    {
        $parts = explode('\\', $qualifiedEntityClass);
        $class = end($parts);

        return explode('_', $class);
    }

    /**
     * Retrieves a list of condition qualified classes.
     *
     * @return array
     *   Array of condition classes.
     *
     * @throws ConditionException
     *   If there is an issue retrieving the conditions.
     */
    private function getAllConditions(): array
    {
        try {
            $conditionFiles = scandir(static::CONDITIONS_DIRECTORY);

            if ($conditionFiles === false) {
                throw new ConditionException('Directory of conditions not found');
            }

            return array_filter(
                array_map(fn($conditionFile) => $this->processConditionFile($conditionFile), $conditionFiles)
            );
        } catch (ConditionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ConditionException($e->getMessage());
        }
    }

    /**
     * Processes conditions recursively and extracts relevant details.
     *
     * @param array $conditions
     *   The conditions to process.
     *
     * @return array
     *   The structured conditions with extracted details.
     */
    private function getConditions(array $conditions): array
    {
        $res = [];

        foreach ($conditions as $condition) {
            if (empty($condition)) {
                continue;
            }

            [$conditionName, $version] = $this->extractEntityAndVersion($condition['class']);

            $res[] = [
                'class' => $condition['class'],
                'conditionName' => $conditionName,
                'version' => $version,
                'options' => $condition['options'] ?? [],
                'subConditions' => !empty($condition['subConditions'])
                    ? $this->getConditions($condition['subConditions'])
                    : [],
            ];
        }

        return $res;
    }

    /**
     * Obtain a list of task classes that possess JSON forms.
     *
     * @return array
     *   Array of task classes.
     *
     * @throws TaskException
     *   If there is an issue with getting the tasks list.
     */
    private function getFilteredRegisteredTasks(): array
    {
        try {
            $taskHandlerManager = ComponentBasedObject::getRootComponentByClassName(TaskHandlerManager::class, true);

            return array_filter($taskHandlerManager->getRegisteredTasks(), function ($task) {
                try {
                    $reflection = new ReflectionClass($task);
                } catch (ReflectionException $e) {
                    throw new Exception("Unable to reflect on class $task.");
                }

                return !$reflection->isAbstract()
                    && $reflection->hasMethod('hasEnableForm')
                    && $reflection->getMethod('hasEnableForm')->invoke(null);
            });
        } catch (\Exception $e) {
            throw new TaskException($e->getMessage());
        }
    }

    /**
     * Retrieves the list of webservices.
     *
     * @return array
     *   The webservices list.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function getWebServices(): array
    {
        if (empty($this->webservices)) {
            $this->updateWebServicesList();
        }

        return $this->webservices;
    }

    /**
     * Retrieves tasks associated with a given web service.
     *
     * @param string $webserviceId
     *   The web service identifier.
     *
     * @return array
     *   An array containing categorized tasks.
     *
     * @throws BadRequestException
     * @throws WebServiceException
     */
    public function getWebServiceTasks(string $webserviceId): array
    {
        // Todo: add authentification.
        $webservice = $this->getWebServices()[$webserviceId] ?? null;

        if (empty($webservice)) {
            throw new BadRequestException('WebService does not exist');
        }

        $eventMapping = [
            'destinationRequestPreparation' => 'tasksOnRequest',
            'startedReceiving' => 'tasksOnResponse',
            // Todo: delete destinationReachFailure. Check with Wilfrid for details.
            'destinationReachFailure' => 'tasksAfterResponse',
            'emittedResponse' => 'tasksAfterResponse',
        ];

        $res = [];

        foreach ($webservice['tasks'] as $event => $tasks) {
            if (empty($eventMapping[$event])) {
                throw new WebServiceException('The webservice s file format is not correct.');
            }

            $category = $eventMapping[$event];

            $res[$category] = array_map(fn($task) => [
                'class' => $task['class'],
                'taskName' => $this->extractEntityAndVersion($task['class'])[0],
                'version' => $this->extractEntityAndVersion($task['class'])[1],
                'options' => $task['options'] ?? [],
                'conditions' => !empty($task['conditions']) ? $this->getConditions($task['conditions']) : [],
            ], $tasks ?? []);
        }

        return $res;
    }

    public function handlePostRequest(): void
    {
        try {
            $wsid = $_GET['wsid'];
            $result = static::load(static::SERVER_YAML_FILE);

            $result['data']['mostRecentTasks'] = $this->mostRecentTasks();
            $result['data']['selectedTaskToAdd'] = $this->mostRecentTasks['Fallback']['class'];
            $result['data']['mostRecentConditions'] = $this->mostRecentConditions();
            $result['data']['selectedConditionToAdd'] = $this->mostRecentConditions['EndpointCondition']['class'];

            $this->setInfos($result, $wsid);
            $this->setTasks($result, $wsid);
            $this->setConditions($result, $wsid);

            Response::sendOk($result);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Checks if a given class is a valid form.
     *
     * @param ReflectionClass $reflection
     *   The class to check.
     *
     * @return bool
     *   True if the class is a valid form, false otherwise
     *
     * @throws ReflectionException
     *   If the reflection operation fails.
     */
    private function isValidForm(ReflectionClass $reflection): bool
    {
        // Skip abstract classes or classes with 'hasEnableForm' method returns false.
        return (!$reflection->isAbstract()
            && $reflection->hasMethod('hasEnableForm')
            && $reflection->getMethod('hasEnableForm')->invoke(null));
    }

    /**
     * Loads and parses a YAML file.
     *
     * @param string $filePath
     *   The path to the YAML file.
     *
     * @return array
     *   The parsed YAML content.
     *
     * @throws Exception
     *   If the file cannot be loaded or parsed.
     */
    public static function load(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("YAML file not found: $filePath");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new Exception("Failed to read YAML file: $filePath");
        }

        return Yaml::parse($content) ?? [];
    }

    /**
     * Retrieves the most recent versions of available conditions.
     *
     * @return array
     *   An array of the most recent condition classes mapped by label.
     *
     * @throws ConditionException
     *   If there is an issue retrieving conditions.
     */
    private function mostRecentConditions(): array
    {
        $mostRecent = [];

        foreach ($this->getAllConditions() as $condition) {
            [$label, $version] = $this->extractEntityAndVersion($condition);
            $escapedClass = $this->escapeClassName($condition);

            if (empty($mostRecent[$label]) || $mostRecent[$label]['lastVersion'] < $version) {
                $mostRecent[$label] = [
                    'lastVersion' => $version,
                    'class' => $escapedClass,
                ];
            }
        }

        $this->mostRecentConditions = $mostRecent;

        return array_map(
            fn($label, $data) => ['label' => $label, 'value' => $data['class'],],
            array_keys($mostRecent),
            array_values($mostRecent),
        );
    }

    /**
     * Retrieves the most recent versions of registered tasks.
     *
     * @return array
     *   Array containing the latest version of each task.
     *
     * @throws TaskException
     *   If there is an issue retrieving the tasks.
     */
    private function mostRecentTasks(): array
    {
        $mostRecent = [];
        foreach ($this->getFilteredRegisteredTasks() as $task) {
            [$label, $version] = $this->extractEntityAndVersion($task);
            $escapedClass = $this->escapeClassName($task);

            if (empty($mostRecent[$label]) || $mostRecent[$label]['lastVersion'] < $version) {
                $mostRecent[$label] = [
                    'lastVersion' => $version,
                    'class' => $escapedClass,
                ];
            }
        }

        $this->mostRecentTasks = $mostRecent;

        return array_map(
            fn($label, $data) => ['label' => $label, 'value' => $data['class'],],
            array_keys($mostRecent),
            array_values($mostRecent),
        );
    }

    /**
     * Processes a condition file and returns its fully qualified class name if valid.
     *
     * @param string $conditionFile
     *   The filename of the condition.
     *
     * @return string|null
     *   The fully qualified class name or null if invalid.
     *
     * @throws ConditionException
     * @throws ReflectionException
     */
    private function processConditionFile(string $conditionFile): ?string
    {
        // Escape non-conditions such as hidden files, etc.
        if (!preg_match('/^([a-zA-Z]+_\d+)\.php$/', $conditionFile, $matches)) {
            return null;
        }

        $conditionClass = static::CONDITIONS_NAMESPACE . $matches[1];

        try {
            $reflection = new ReflectionClass($conditionClass);
        } catch (ReflectionException $e) {
            throw new ConditionException("Unable to reflect on class $conditionClass.");
        }

        if (!$this->isValidForm($reflection)) {
            return null;
        }

        return $conditionClass;
    }

    /**
     * Sets the condition forms and updates the result array with condition-related data.
     *
     * @param array $result
     *   The reference array to store condition forms.
     * @param string $wsid
     *   The web service identifier.
     *
     * @throws ConditionException
     */
    private function setConditions(array &$result, string $wsid)
    {
        foreach ($this->getAllConditions() as $conditionClass) {
            if (!class_exists($conditionClass) || !$conditionClass::hasEnableForm()) {
                continue;
            }

            $escapedClass = $this->escapeClassName($conditionClass);
            $conditionDefinition = $conditionClass::getFormDefinition('~~._temp_edit_condition');

            $result['listForms'][$escapedClass] = $conditionDefinition['renderView'];
            $result['listForms'] += !empty($conditionDefinition['listForms']) ? $conditionDefinition['listForms'] : [];
            $result['listForms']['condition_item_row']['content'][2]['content'][1]['body'][0]['content'][] = [
                'type' => 'Phantom',
                'actions' => [['what' => 'hide', 'when' => '~.class', 'isNot' => $escapedClass,],],
                'content' => ['load' => $escapedClass, 'keepTemplateContext' => true,],
            ];
        }

        foreach ($this->mostRecentConditions as $key => $condition) {
            $result['listForms']['conditionsListForm'][0]['content'][2]
            ['content']['content']['1']['actions'][] = [
                'what' => 'addData',
                'on' => 'click',
                'when' => '~~.selectedConditionToAdd',
                'is' => $condition['class'],
                'path' => '~~._temp_edit_task.conditions',
                'value' => [
                    'class' => $condition['class'],
                    'conditionName' => $key,
                    'version' => $condition['lastVersion'],
                ],
            ];
        }
    }

    /**
     * Sets the basic information for the specified web service.
     *
     * @param array $result
     *   The reference array to store web service information.
     * @param $wsid
     *   The web service identifier.
     *
     * @throws WebServiceException
     *   If there is an issue retrieving the web service data.
     */
    private function setInfos(array &$result, $wsid): void
    {
        $listWebServices = $this->getWebServices();

        if (empty($listWebServices)) {
            return;
        }

        $webservice = $listWebServices[$wsid];

        $result['data']['name'] = $webservice['name'] ?? '';
        $result['data']['domain'] = $webservice['domain'] ?? '';
        $result['data']['wsid'] = $webservice['wsid'] ?? '';
    }

    /**
     * Sets the tasks for the given web service and prepares task-related forms.
     *
     * @param array $result
     *   The reference array to store task information.
     * @param string $wsid
     *   The web service identifier.
     *
     * @throws BadRequestException
     * @throws TaskException
     * @throws WebServiceException
     */
    private function setTasks(array &$result, string $wsid)
    {
        $result['data']['tasks'] = $this->getWebServiceTasks($wsid);

        foreach ($this->getFilteredRegisteredTasks() as $taskClass) {
            if (!class_exists($taskClass) || !$taskClass::hasEnableForm()) {
                continue;
            }

            $escapedClass = $this->escapeClassName($taskClass);
            $taskDefinition = $taskClass::getFormDefinition('~~._temp_edit_task');

            $result['listForms'][$escapedClass] = $taskDefinition['renderView'];
            $result['listForms'] += !empty($taskDefinition['listForms']) ? $taskDefinition['listForms'] : [];

            $result['listForms']['task_item_row']['content'][2]['content'][1]['body'][0]['content'][] = [
                'type' => 'Phantom',
                'actions' => [['what' => 'hide', 'when' => '~.class', 'isNot' => $escapedClass,],],
                'content' => ['load' => $escapedClass, 'keepTemplateContext' => true,],
            ];
        }

        $taskFormMapping = [
            'tasksOnRequest' => 'actOnRequestForm',
            'tasksOnResponse' => 'actOnResponseForm',
            'tasksAfterResponse' => 'actAfterResponseForm',
        ];

        foreach ($this->mostRecentTasks as $key => $task) {
            foreach ($taskFormMapping as $taskType => $taskForm) {
                $result['listForms'][$taskForm][0]['content'][1]['content'][2]
                ['content']['content']['1']['actions'][] = [
                    'what' => 'addData',
                    'on' => 'click',
                    'when' => '~~.selectedTaskToAdd',
                    'is' => $task['class'],
                    'path' => '~~.tasks.' . $taskType,
                    'value' => [
                        'class' => $task['class'],
                        'taskName' => $key,
                        'version' => $task['lastVersion'],
                    ],
                ];
            }
        }
    }

    /**
     * Update the list of webservices directly from the files of config directory.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function updateWebServicesList(): void
    {
        $yamlFiles = glob(static::WEBSERVICES_DIRECTORY . "/*.yaml");

        unset($this->webservices);

        foreach ($yamlFiles as $yamlFile) {
            $webserviceContent = file_get_contents($yamlFile);

            if ($webserviceContent === false) {
                throw new WebServiceException('Impossible to load the web service.');
            }

            $parsedWebService = Yaml::parse($webserviceContent);

            $wsid = $parsedWebService['wsid'];

            $this->webservices[$wsid] = $parsedWebService;
        }
    }
}

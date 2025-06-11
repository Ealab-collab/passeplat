<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition\ConditionBase;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition\ConditionManager;

/**
 * Executes tasks as asked.
 */
class TaskHandlerManager extends ComponentBasedObject
{
    /**
     * A list of active task handler class names.
     *
     * @var class-string<TaskHandlerBase>[]
     */
    private array $activeTaskHandlers = [];

    /**
     * @var ConditionManager|null
     */
    private ?ConditionManager $conditionManager;

    /**
     * Executes tasks.
     *
     * Should not break when a task fails.
     *
     * @param AnalyzableContent $analyzableContent
     *   Contains data to read / edit.
     * @param array $taskConfigs
     *   Configuration of the tasks to run.
     *
     * @param string $eventName
     *
     */
    public function executeTasks(AnalyzableContent $analyzableContent, array $taskConfigs, string $eventName): void
    {
        foreach ($taskConfigs as $taskConfig) {
            if (empty($taskConfig['class'])) {
                // Todo: alerte à remonter à l'utilisateur pour qu'il mette à jour sa config.
                continue;
            }

            try {
                // Load the task handler.
                /** @var TaskHandlerBase $taskHandler */
                $taskHandler = $this->getComponentByClassName($taskConfig['class'], true);
            } catch (UnmetDependencyException $exception) {
                // Fail safe execution. Ignore this class.
                // Todo: problème de code, à remonter aux devs.
                continue;
            }

            if (empty($taskHandler)) {
                // Class unavailable. Config is outdated or invalid.
                // Todo: alerte à remonter à l'utilisateur pour qu'il mette à jour sa config.
                continue;
            }

            if (!empty($taskConfig['conditions'])
                && !$this->isAllowed($analyzableContent, $taskHandler, $taskConfig['conditions'])) {
                // Todo: information à remonter pour l'utilisateur.
                continue;
            }

            $taskOptions = !empty($taskConfig['options']) && is_array($taskConfig['options'])
                ? $taskConfig['options']
                : [];

            try {
                $taskHandler->execute($analyzableContent, $taskOptions, $eventName);
            } catch (\Exception $exception) {
                // Fail safe execution of tasks. Could be reported.
                // Todo: information à remonter pour l'utilisateur, peut-être pour les devs aussi.
                continue;
            }
        }
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(ConditionManager::class, $this->conditionManager);
        return $definitions;
    }

    /**
     * Gets the list of task handlers.
     *
     * @return \class-string[]
     */
    public function getRegisteredTasks(): array
    {
        return $this->activeTaskHandlers;
    }

    /**
     * Checks conditions of a task.
     *
     * @param AnalyzableContent $analyzableContent
     *   The object containing the values which may be read/edited.
     * @param TaskHandlerBase $taskHandler
     *   The task handler for which the conditions should be checked.
     * @param array $conditions
     *   The conditions to check.
     *
     * @return bool
     *   True if the task is allowed to execute.
     */
    private function isAllowed(
        AnalyzableContent $analyzableContent,
        TaskHandlerBase $taskHandler,
        array $conditions = []
    ): bool {
        if (empty($conditions)) {
            // No conditions mean the task is allowed to execute unconditionally.
            return true;
        }

        $this->conditionManager->buildConditionsTree($taskHandler, $conditions);

        /** @var ConditionBase[] $conditionRoots */
        $conditionRoots = $taskHandler->getComponentsByClassName(ConditionBase::class);

        foreach ($conditionRoots as $conditionRoot) {
            try {
                if ($conditionRoot->evaluate($analyzableContent)) {
                    // A conditions chain has been fully evaluated to true.
                    return true;
                }
            } catch (ConditionException $e) {
                // Error while evaluating the condition. It may be due to invalid options.
                // Ignore this conditions chain.
                // Todo: Ça pourrait être remonté pour l'utilisateur.
                continue;
            }
        }

        // No conditions chain has been fully evaluated to true.
        return false;
    }

    /**
     * Registers a task handler class.
     *
     * @param string $taskHandlerClassName
     *   The fully qualified class name of the task handler to register.
     */
    public function registerTaskHandler(string $taskHandlerClassName): void
    {
        $this->activeTaskHandlers[] = $taskHandlerClassName;
    }
}

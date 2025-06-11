<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;

/**
 * Base class for condition plugins.
 */
abstract class ConditionBase extends ComponentBasedObject
{
    use ExecutionTraceProviderTrait;

    /**
     * Live options of this condition instance.
     */
    protected array $options = [];

    /**
     * The condition plugin machine name.
     *
     * @var string
     */
    protected string $pluginId;

    /**
     * Adds details to the execution trace of this condition.
     *
     * @param array $details
     *   The details to add.
     */
    protected function addConditionExecutionTraceDetails(array $details): void
    {
        $details['id'] = $this->pluginId;
        $this->addExecutionTraceDetails($details);
    }

    /**
     * Add string to the execution trace of this condition.
     *
     * @param string $string
     *   The string to add.
     */
    protected function addConditionExecutionTraceString(string $string): void
    {
        $this->addConditionExecutionTraceDetails([
            'info' => $string,
        ]);
    }

    /**
     * Evaluates the conditions chains applied on this condition.
     *
     * @param AnalyzableContent $analyzableContent
     *   The analyzable content.
     *
     * @return bool
     *   The condition result:
     *   - If a conditions chain is fully validated, the result is true.
     *   - Otherwise, the result is false.
     *
     * @throws ConditionException
     *   If the condition could not be evaluated.
     */
    public function evaluate(AnalyzableContent $analyzableContent): bool
    {
        $this->addConditionExecutionTraceString('Started evaluating condition...');

        if (!$this->invertResultIfNecessary($this->selfEvaluate($analyzableContent))) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
This condition is unmet, so the final result is NOT ALLOWED.
INFO);
            return false;
        }

        $subConditions = $this->getSubConditions();

        if (empty($subConditions)) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
This condition is met, and has no subconditions.
The conditions chain is complete, so the final result is ALLOWED.
INFO);
            return true;
        }

        $subConditionCount = count($subConditions);
        // Todo: traduction.
        $this->addConditionExecutionTraceString(<<<INFO
This condition is met.
$subConditionCount subcondition(s) found. Checking them...
INFO);

        foreach ($subConditions as $subCondition) {
            try {
                if (!$subCondition->evaluate($analyzableContent)) {
                    // Todo: traduction.
                    $this->addConditionExecutionTraceString(<<<INFO
Subconditions chain evaluated to NOT ALLOWED.
Continuing to evaluate sibling subconditions.
INFO);
                    continue;
                }
            } catch (ConditionException $e) {
                // The subcondition could not be evaluated.
                // Todo: traduction.
                $this->addConditionExecutionTraceString(<<<INFO
Continuing to evaluate sibling subconditions.
INFO);
                continue;
            }

            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
Subcondition evaluated to ALLOWED.
The subconditions chain is complete, so the final result is ALLOWED.
INFO);
            return true;
        }

        // Todo: traduction.
        $this->addConditionExecutionTraceString(<<<INFO
Exhausted subconditions list for this condition.
No subcondition chain evaluated to ALLOWED, so the final result is NOT ALLOWED.
INFO);
        return false;
    }

    /**
     * Get an array corresponding to a 'data.json' to construct a form using jsonForms.io.
     *
     * If the method does not need to be overridden,
     * it solicits the method of the condition from the immediately preceding version.
     *
     * @param array|null $providedData
     *   The provided data replaces the default values typically assigned by the web service.
     *
     * @return array
     *   An array corresponding to a 'data.json'.
     *
     * @throws ConditionException
     *   If the method is not overridden in version zero of the condition.
     */
    public static function getFormData(?array $providedData): array
    {
        /** @var class-string<ConditionBase> $previousCondition */
        $previousCondition = static::getPreviousConditionClassName();

        return $previousCondition::getFormData($providedData);
    }

    // TODO: Branding - jsonToReact.
    /**
     * Returns an array containing the 'renderView' and 'listForms'
     * used by jsonToReact for rendering the condition form.
     *
     * If the method does not need to be overridden,
     * it solicits the method of the condition from the immediately preceding version.
     *
     * @param string $rootPath
     *   The root path that defines the data path scope used by the condition form.
     *
     * @return array
     *   An array containing two keys: 'renderView' and 'listForms'
     *
     * @throws ConditionException
     */
    public static function getFormDefinition(string $rootPath = '~'): array
    {
        /** @var class-string<ConditionBase> $previousCondition */
        $previousCondition = static::getPreviousConditionClassName();

        return $previousCondition::getFormDefinition($rootPath);
    }

    /**
     * Gets the value for the given key from the instance options.
     *
     * @param string $key
     *   The key to get the value for.
     *
     * @return mixed
     *   The value for the given key.
     *
     * @throws ConditionException
     *   If the value could not be retrieved from the instance options.
     */
    protected function getOption(string $key)
    {
        if (!array_key_exists($key, is_array($this->options) ? $this->options : [])) {
            throw new ConditionException(
                "Missing option '$key' from the condition plugin options. Condition plugin: " . static::class
            );
        }

        return $this->options[$key];
    }

    /**
     * Gets the plugin description.
     *
     * @return array
     *   The plugin description.
     */
    protected function getPluginDescription(): array
    {
        return [
            'class' => static::class,
            // The public name shown to the final user.
            'publicName' => null,
            // A short machine name used internally.
            'id' => null,
            // The plugin version.
            'version' => null,
            // A list of categories this plugin may be used in.
            'appliesTo' => [],
            'optionsSchema' => [
                // The base of the schema implicitly lists properties of an object type entry.
                'invertResult' => [
                    'type' => 'bool',
                    'default' => false,
                ],
            ],
        ];
    }

    /**
     * Gets the id (machine name) of this plugin.
     *
     * @return mixed
     *   The value for the given key.
     *
     * @throws ConditionException
     *   If the value could not be retrieved from the plugin description.
     */
    protected function getPluginId(): string
    {
        $id = $this->getPluginDescription()['id'] ?? null;

        if (empty($id)) {
            throw new ConditionException(
                'Missing id from the condition plugin description. Condition plugin: ' . static::class
            );
        }

        return $id;
    }

    /**
     * Gets the full classname of the previous condition.
     *
     * @return class-string<ConditionBase>|null
     *   The full classname of the previous condition.
     *
     * @throws ConditionException
     *   This class has no previous version available, or is not a versioned class.
     */
    public static function getPreviousConditionClassName(): ?string
    {
        $class = static::class;

        // First, make sure the current class is compatible with the class version system.
        [$className, $classVersion] = explode('_', $class, 2);

        $classVersion = filter_var($classVersion, FILTER_VALIDATE_INT);

        if ($classVersion === false || $classVersion < 1) {
            // This class has an invalid or missing version number.
            throw new ConditionException(
                "$class is not a versioned class."
            );
        }

        // Iterate over decreasing version numbers until we find an existing class.
        $previousClassName = null;
        $previousClassVersion = $classVersion;

        while ($previousClassVersion >= 1) {
            --$previousClassVersion;

            $maybePreviousClassName = $className . '_' . $previousClassVersion;

            if (class_exists($maybePreviousClassName)) {
                $previousClassName = $maybePreviousClassName;
                break;
            }
        }

        if (!$previousClassName) {
            throw new ConditionException(
                "$class has no previous version."
            );
        }

        return $previousClassName;
    }

    /**
     * Gets the sub conditions.
     *
     * @return ConditionBase[]
     *   The sub conditions.
     */
    protected function getSubConditions(): array
    {
        return $this->getComponentsByClassName(ConditionBase::class);
    }

    /**
     * Checks whether the condition has a form or not.
     *
     * By default, each condition has a form.
     *
     * @return bool
     */
    public static function hasEnableForm(): bool
    {
        return true;
    }

    /**
     * Initializes the condition.
     *
     * Override this method to add custom initialization logic.
     *
     * @param array $options
     *   Options for the condition.
     *
     * @return void
     */
    public function initialize(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Inverts the result if necessary.
     *
     * @param bool $result
     *   The result to invert.
     *
     * @return bool
     *   The inverted result if necessary.
     *
     * @throws ConditionException
     */
    protected function invertResultIfNecessary(bool $result): bool
    {
        if (!empty($this->getOption('invertResult'))) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
Inverting result because the condition is configured to do so.
INFO);
        }
        return $this->getOption('invertResult') ? !$result : $result;
    }

    /**
     * @throws ConditionException
     */
    public function onReady(): void
    {
        $this->pluginId = $this->getPluginId();
    }

    /**
     * Replace the default data of a jsonForms condition with the provided data only for existing keys.
     *
     * @param array $defaultData
     *   The default data array.
     * @param array|null $providedData
     *   The provided data array.
     *
     * @return array
     *   The merged data array.
     */
    public static function replaceFormData(array $defaultData, ?array $providedData): array
    {
        $mergedData = $defaultData;

        if (!empty($providedData)) {
            foreach ($providedData as $key => $value) {
                if ($key !== 'options' && isset($defaultData[$key])) {
                    $mergedData[$key] = $value;
                }

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

    /**
     * Evaluation for this condition.
     *
     * The result may be inverted by $invertResult.
     *
     * @param AnalyzableContent $analyzableContent
     *   The analyzable content.
     *
     * @return bool
     *   true if the condition is met, false otherwise.
     *
     * @throws ConditionException
     *   If the condition cannot be evaluated.
     */
    abstract protected function selfEvaluate(AnalyzableContent $analyzableContent): bool;
}

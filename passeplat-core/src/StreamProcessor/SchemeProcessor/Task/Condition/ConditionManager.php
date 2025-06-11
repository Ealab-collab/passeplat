<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;

/**
 * Useful methods to manage conditions, build trees, etc.
 */
class ConditionManager extends ComponentBasedObject
{
    /**
     * Builds the conditions tree.
     *
     * @param TaskHandlerBase|ConditionBase $ancestor
     *   The ancestor of the conditions to build.
     *   It must be a TaskHandlerBase or a ConditionBase.
     * @param array $conditions
     *   The conditions to build.
     */
    public function buildConditionsTree(
        $ancestor = null,
        array $conditions = []
    ): void {
        if (!($ancestor instanceof TaskHandlerBase) && !($ancestor instanceof ConditionBase)) {
            // Attempted to add a condition to an invalid ancestor.
            return;
        }

        foreach ($conditions as $condition) {
            if (empty($condition['status']) || $condition['status'] !== 'normal') {
                // The condition has been disabled by the user.
                // This will disable the whole chain of conditions starting from this condition.
                // Todo: on peut dans un second temps imaginer un système qui permettrait de choisir de bloquer
                //   la chaîne de conditions à partir de ce point ou de la continuer.
                //   On aurait donc plusieurs choix possibles :
                //   - fonctionnement normal, on ajoute la condition pour évaluation.
                //   - bloquer les chaînes de conditions à partir de ce point, donc on ne va pas plus loin
                //     pour la création de l'arbre. On remplacerait donc ce maillon par une condition système
                //     qui renverrait toujours false.
                //   - continuer les chaînes de conditions à partir de ce point. On remplacerait donc ce maillon
                //     par une condition système qui renverrait toujours true.
                continue;
            }

            $class = $condition['class'] ?? null;

            if (empty($class) || !class_exists($class) || !(is_a($class, ConditionBase::class, true))) {
                continue;
            }

            try {
                // Add the condition as a child of the ancestor.
                $conditionInstance = $ancestor->addComponentByClassName($class);
                $conditionInstance->initialize($condition['options'] ?? []);
            } catch (ConditionException $e) {
                // The given options are invalid. Do not use this condition.
                // Other sibling conditions chains may still be valid for evaluation.
                continue;
            } catch (UnmetDependencyException $e) {
                // The condition class is unavailable. Do not use this condition.
                continue;
            }

            // At this point, the condition instance is valid. Let's build sub-conditions if any.
            if (!empty($condition['subConditions'])) {
                $this->buildConditionsTree($conditionInstance, $condition['subConditions']);
            }
        }
    }
}

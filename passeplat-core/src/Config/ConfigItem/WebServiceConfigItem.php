<?php

namespace PassePlat\Core\Config\ConfigItem;

use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Exception\UserException;
use PassePlat\Core\User\UserManager;
use PassePlat\Core\User\UserManagerInterface;
use PassePlat\Core\WebService\WebServiceManager;

/**
 * Contains user config.
 */
class WebServiceConfigItem extends ConfigItem
{
  
  protected $webserviceManager;

  public function getConfigId(): string
  {
    return 'webservice.passeplat-core';
  }

  public function getDependencyDefinitions(): array
  {
    $definitions = parent::getDependencyDefinitions();
    $definitions[] = new RootDependencyDefinition(WebServiceManager::class, $this->webserviceManager);
    return $definitions;
  }

  public function setValues(array $values): void
  {
    unset($this->config);

    if (empty($values)) {
      return;
    }

    $this->config = $values;

    if (!empty($values['repository']['type'])) {
      if (!($userManager = $this->webserviceManager)) {
        // No user manager.
        return;
      }

      try {
        // Get the user manager and set the repository type.
        $userManager->setRepositoryType($values['repository']['type']);
      } catch (UserException $e) {
        // No repository will be set.
        return;
      }
    }
  }
}

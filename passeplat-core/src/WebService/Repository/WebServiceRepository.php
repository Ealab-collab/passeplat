<?php

namespace PassePlat\Core\WebService\Repository;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\User\Exception\UserRepositoryException;

/**
 * Base class for user repositories.
 */
abstract class WebServiceRepository extends ComponentBasedObject
{

	abstract 	public function loadWebServiceConfigFromFileSystem(string $webServiceId, string $userId): array;

}

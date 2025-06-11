<?php

namespace PassePlat\Openapi\Tool\OpenApi;

/**
 * This interface defines the strategies for updating an openapi specification file.
 */
interface SpecUpdaterStrategy
{
    /**
     * Updates the OpenAPI specification using the specified strategy.
     *
     * @param array $params
     *   Parameters and data specific to the update strategy.
     */
    public function update(array $params):void;
}

<?php

namespace PassePlat\Core;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Exception\HttpHeaderException;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface for Response objects.
 *
 * Those objects are used for service responses to the caller.
 */
interface PassePlatResponseFactoryInterface
{
    /**
     * Emits a response for when the destination service failed to respond.
     *
     * @param AnalyzableContent|null $analyzableContent
     *   Analyzable content. Optional if we don't / can't track the results.
     * @param callable|null $tasksBeforeEmit
     *   Tasks to execute before emit. Optional.
     *
     * @throws HttpHeaderException
     * @throws UnmetDependencyException
     */
    public function emitDestinationFailureResponse(
        AnalyzableContent $analyzableContent = null,
        callable $tasksBeforeEmit = null
    );

    /**
     * Emits a response based on a ResponseInterface object.
     *
     * @param ResponseInterface $response
     *   The response object which contains the info to emit.
     * @param AnalyzableContent|null $analyzableContent
     *   An object which contains loggable content for analysis.
     * @param callable|null $tasksBeforeEmit
     *   A function executed right before emit.
     *
     * @throws HttpHeaderException
     * @throws UnmetDependencyException
     */
    public function emitResponse(
        ResponseInterface $response,
        AnalyzableContent $analyzableContent = null,
        callable $tasksBeforeEmit = null
    );
}

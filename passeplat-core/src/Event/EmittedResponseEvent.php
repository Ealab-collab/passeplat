<?php

namespace PassePlat\Core\Event;

use Dakwamine\Component\Event\EventInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Event for emitted response.
 */
class EmittedResponseEvent implements EventInterface
{
    const EVENT_NAME = 'PASSEPLAT_CORE__EMITTED_RESPONSE';

    /**
     * The emitted response.
     *
     * @var ResponseInterface
     */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function getName(): string
    {
        return static::EVENT_NAME;
    }

    /**
     * Gets the emitted response object.
     *
     * @return ResponseInterface
     *   The emitted response.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}

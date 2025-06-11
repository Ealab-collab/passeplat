<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Base exception for PassePlat.
 */
class Exception extends \Exception
{
    /**
     * PassePlat code.
     *
     * @var string
     *   Passeplat error code.
     */
    private $ppCode;

    public function __construct($message = "", Throwable $previous = null, $ppCode = ErrorCode::PP, $code = 0)
    {
        $this->ppCode = $ppCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets the list of error codes threw by PassePlat.
     *
     * @return array
     *   Array of PassePlat error codes.
     */
    public function getPpCodeChain()
    {
        $ppCodes = [];
        $previous = $this->getPrevious();
        if (!empty($previous) && $previous instanceof Exception) {
            $ppCodes = $previous->getPpCodeChain();
        }

        if (!empty($this->ppCode)) {
            $ppCodes[] = $this->ppCode;
        }

        return $ppCodes;
    }

    /**
     * Gets the list of error codes threw by PassePlat as a string.
     *
     * @return string
     *   String of PassePlat error codes.
     */
    public function getPpCodeChainString()
    {
        return implode(',', $this->getPpCodeChain());
    }
}

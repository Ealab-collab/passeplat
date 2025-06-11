<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use PassePlat\Core\Exception\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Status about a HTTP web service call.
 */
class HttpWebServiceStatus extends WebServiceStatusBase
{
    const HTTP_1XX = '1XX';
    const HTTP_2XX = '2XX';
    const HTTP_3XX = '3XX';
    const HTTP_4XX = '4XX';
    const HTTP_5XX = '5XX';

    /**
     * Special statuses used when the HTTP status code was replaced by PassePlat.
     * This is useful when the response was served by PassePlat in a fallback scenario.
     */

    const HTTP_1WS = '1WS';
    const HTTP_2WS = '2WS';
    const HTTP_3WS = '3WS';
    const HTTP_4WS = '4WS';
    const HTTP_5WS = '5WS';

    protected function getValidStatuses(): array
    {
        $statuses = parent::getValidStatuses();
        $statuses[] = static::HTTP_1XX;
        $statuses[] = static::HTTP_2XX;
        $statuses[] = static::HTTP_3XX;
        $statuses[] = static::HTTP_4XX;
        $statuses[] = static::HTTP_5XX;
        $statuses[] = static::HTTP_1WS;
        $statuses[] = static::HTTP_2WS;
        $statuses[] = static::HTTP_3WS;
        $statuses[] = static::HTTP_4WS;
        $statuses[] = static::HTTP_5WS;
        return $statuses;
    }

    /**
     * Gets the WS status counterpart of the given status.
     *
     * @param string $status
     *   Status code. Use one of the available constants.
     * @param string $suffix
     *   Suffix to be added to the status, like XX or WS.
     *
     * @throws Exception
     *   The given status is invalid.
     */
    private function getPassePlatStatusByHttpStatus(string $status, string $suffix): string
    {
        if (strlen($status) !== 3) {
            throw new Exception(<<<EOT
The given status is invalid.
EOT
            );
        }

        $responseHttpCodeFirstCharacter = substr($status, 0, 1);

        if (!in_array($responseHttpCodeFirstCharacter, ['1', '2', '3', '4', '5'], true)) {
            throw new Exception(<<<EOT
The given status is invalid. It must start with 1, 2, 3, 4 or 5.
EOT
            );
        }
        return constant("static::HTTP_${responseHttpCodeFirstCharacter}${suffix}");
    }

    /**
     * Gets the WS status counterpart of the given status.
     *
     * @param string $status
     *   Status code.
     *
     * @throws Exception
     *   The given status is invalid.
     */
    public function getWsStatusByHttpStatus(string $status): string
    {
        return $this->getPassePlatStatusByHttpStatus($status, 'WS');
    }

    /**
     * Gets the XX status counterpart of the given status.
     *
     * @param string $status
     *   Status code. Use one of the available constants.
     *
     * @throws Exception
     *   The given status is invalid.
     */
    public function getXxStatusByHttpStatus(string $status): string
    {
        return $this->getPassePlatStatusByHttpStatus($status, 'XX');
    }

    /**
     * Sets the status using a response object.
     *
     * @param ResponseInterface $response
     *   The response object.
     * @param bool $overwriteIfSet
     *   Set to true to overwrite the current value if already set.
     * @param string $suffix
     *   Suffix to be added to the status, like XX or WS.
     *
     * @throws Exception
     *   Invalid response given.
     */
    public function setStatusFromResponse(
        ResponseInterface $response,
        bool $overwriteIfSet = false,
        $suffix = 'XX'
    ): void {
        if ($this->isAlreadySet() && !$overwriteIfSet) {
            // The status code is already set and we don't want to overwrite it.
            return;
        }

        $this->setStatus(
            $this->getPassePlatStatusByHttpStatus((string) $response->getStatusCode(), $suffix),
            $overwriteIfSet
        );
    }
}

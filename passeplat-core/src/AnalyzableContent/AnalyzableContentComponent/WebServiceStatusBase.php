<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use PassePlat\Core\Exception\Exception;

/**
 * Status about a web service call. To extend for each web service type (http, ftp, etc).
 */
abstract class WebServiceStatusBase extends AnalyzableContentComponentBase
{
    const UNSET = 'UNSET';
    const NOT_REACHABLE = 'NOT_REACHABLE';

    /**
     * The status. Use one of the constants WebServiceStatus::*.
     *
     * @var string
     */
    protected $status;

    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        if (empty($this->status)) {
            return array_merge($data, ['web_service_status' => static::UNSET]);
        }

        return array_merge($data, ['web_service_status' => $this->status]);
    }

    /**
     * Gets valid statuses list.
     *
     * @return string[]
     *   A list of statuses.
     */
    protected function getValidStatuses(): array
    {
        return [
            static::NOT_REACHABLE,
        ];
    }

    /**
     * Tells if the status has already been set.
     *
     * @return bool
     *   True if set, false otherwise.
     */
    public function isAlreadySet(): bool
    {
        return !empty($this->status);
    }

    /**
     * Sets the status.
     *
     * @param string $status
     *   The status to set. Use one of the WebServiceStatus::* constants.
     * @param bool $overwriteIfSet
     *   Set to true to overwrite the current value if already set.
     *
     * @throws Exception
     *   Invalid status given.
     */
    public function setStatus(string $status, bool $overwriteIfSet = false): void
    {
        if (!in_array($status, $this->getValidStatuses(), true)) {
            throw new Exception('Invalid status given in WebServiceStatus::setStatus.');
        }

        if ($this->isAlreadySet() && !$overwriteIfSet) {
            return;
        }

        $this->status = $status;
    }
}

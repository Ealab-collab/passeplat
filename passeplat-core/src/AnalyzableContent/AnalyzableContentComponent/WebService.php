<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use PassePlat\Core\WebService\WebServiceInterface;

/**
 * Contains web service info.
 */
class WebService extends AnalyzableContentComponentBase
{
    /**
     * The web service for this component.
     *
     * @var WebServiceInterface
     */
    private WebServiceInterface $webService;

    /**
     * {@inheritdoc}
     */
    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        if (empty($this->webService)) {
            // Nothing to save here.
            return $data;
        }

        $data['passeplat_wsid'] = $this->webService->getWebServiceId();

        // In theory, it is impossible to log an item with an empty "accessing user" because only authenticated requests
        // are processed. But let's check it anyway because the accessingUser property is not mandatory on the
        // WebService object (defaults to null on instantiation if not set).
        // If needed, an empty accessingUser value could be treated as an exception to throw.
        if ($this->webService->getAccessingUser() !== null) {
            $data['passeplat_uid'] = $this->webService->getAccessingUser()->getId();
        }

        return $data;
    }

    /**
     * Gets the web service for this component.
     *
     * @return WebServiceInterface
     */
    public function getWebService(): WebServiceInterface
    {
        return $this->webService;
    }

    /**
     * Sets the web service for this component.
     *
     * @param WebServiceInterface $webService
     *   Web service.
     */
    public function setWebService(WebServiceInterface $webService)
    {
        $this->webService = $webService;
    }
}

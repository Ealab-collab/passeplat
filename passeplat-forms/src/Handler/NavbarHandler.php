<?php

namespace PassePlat\Forms\Handler;

use PassePlat\Forms\Exception\WebServiceException;
use PassePlat\Forms\Vue\Response;
use Symfony\Component\Yaml\Yaml;

class NavbarHandler extends Handler
{
    /**
     * Path to the backend YAML configuration file.
     */
    const SERVER_YAML_FILE = './../passeplat-forms/blueprint/navbar.yaml';

    private const WEBSERVICES_DIRECTORY = '../config/app/webservice/';

    private array $webservices = [];

    /**
     * Retrieves the cached list of web services, updating it if necessary.
     *
     * @return array
     *   The list of web services.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function getWebServices(): array
    {
        if (empty($this->webservices)) {
            $this->updateWebServicesList();
        }

        return $this->webservices;
    }

    public function handlePostRequest(): void
    {
        try {
            $wsid = $_GET['wsid'] ?? '';
            $activeTab = $_GET['activeTab'] ?? '';
            $locked = !(!empty($wsid) && in_array($activeTab, ['dashboard', 'errors', 'logs', 'edit'], true));

            $absoluteYamlFilePath = realpath(self::SERVER_YAML_FILE);

            if (!$absoluteYamlFilePath) {
                throw new \Exception('Invalid YAML file path.');
            }

            $yamContent = Yaml::parse(file_get_contents($absoluteYamlFilePath));

            if (!$locked) {
                $this->setHrefs($yamContent, $wsid);
                $yamContent['data']['locked'] = false;
            }

            $this->setWebServices($yamContent, $wsid, $locked);

            Response::sendOk($yamContent);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Retrieves the list of all available web services.
     *
     * @return array
     *   An array containing web service IDs.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    public function listWebServices(): array
    {
        $webservices = $this->getWebServices();

        unset($webservices['default']);

        return array_keys($webservices);
    }

    /**
     * Updates href attributes in the YAML content based on the selected web service ID.
     *
     * @param array $yamContent
     *   Reference to the YAML content array.
     * @param string $wsid
     *   The selected web service ID.
     */
    private function setHrefs(array &$yamContent, string $wsid)
    {
        foreach ([0, 1, 2, 3] as $index) {
            $yamContent['renderView'][0]['content'][0]['content'][0]['content'][2]['content'][0]['content']
            [$index]['content'][0]['attributes']['href'] .= '?wsid=' . $wsid;
        }
    }

    /**
     * Populates the result array with the list of available web services.
     *
     * @param array $result
     *   Reference to the result array to populate.
     * @param string $wsid
     *   The selected web service ID.
     * @param bool $locked
     *   Whether the right side of the navbar is locked.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function setWebServices(array &$result, string $wsid, bool $locked)
    {
        $listWebServices = $this->listWebServices();

        $formattedWebServices[] = [
            'label' => '-- List of my webservices --',
            'value' => ($locked || empty($wsid)) ? 'All' : '/build/webservices.php',
        ];

        foreach ($listWebServices as $webservice) {
            $formattedWebServices[] = [
                'label' => $this->webservices[$webservice] ['name'] ?? 'undefined title',
                'value' => ($webservice === $wsid) ? 'All' : '/build/dashboard.php?wsid=' . $webservice,
            ];
        }

        $result['data']['webservices'] = $formattedWebServices;
        $result['data']['selectedWebservice'] = 'All';
    }

    /**
     * Update the list of webservices directly from the files of config directory.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function updateWebServicesList(): void
    {
        $yamlFiles = glob(static::WEBSERVICES_DIRECTORY . "/*.yaml");

        unset($this->webservices);
        foreach ($yamlFiles as $yamlFile) {
            $webserviceContent = file_get_contents($yamlFile);

            if ($webserviceContent === false) {
                throw new WebServiceException('Impossible to load the web service.');
            }

            $parsedWebService = Yaml::parse($webserviceContent);
            $this->webservices[$parsedWebService['wsid']] = $parsedWebService;
        }
    }
}

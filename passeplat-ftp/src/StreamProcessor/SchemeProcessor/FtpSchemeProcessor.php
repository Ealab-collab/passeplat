<?php

namespace PassePlat\Ftp\StreamProcessor\SchemeProcessor;

use Dakwamine\Component\RootDependencyDefinition;
use GuzzleHttp\Psr7\Uri;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\DestinationResponseBodyAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;
use PassePlat\Core\PassePlatResponseFactory;
use PassePlat\Core\PassePlatResponseFactoryInterface;
use PassePlat\Core\StreamProcessor\SchemeProcessor\SchemeProcessor;
use PassePlat\Core\WebService\WebServiceInterface;
use PassePlat\Ftp\AnalyzableContent\AnalyzableContentComponent\FtpWebServiceStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FTP scheme processor.
 */
class FtpSchemeProcessor extends SchemeProcessor
{
    /**
     * PassePlat response factory.
     *
     * @var PassePlatResponseFactoryInterface
     */
    private $passePlatResponseFactory;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(PassePlatResponseFactory::class, $this->passePlatResponseFactory);
        return $definitions;
    }

    protected function getHandledSchemes(): array
    {
        return ['ftp'];
    }

    public function processRequest(
        ServerRequestInterface $request,
        AnalyzableContent $analyzableContent,
        $destinationUrl,
        WebServiceInterface $webService
    ): void {
        /** @var FtpWebServiceStatus $webServiceStatus */
        $webServiceStatus = $analyzableContent->addComponentByClassName(FtpWebServiceStatus::class);

        // TODO: only file pull is supported right now.
        /** @var Timing $destinationResponseWaitComponent */
        $destinationResponseWaitComponent = $analyzableContent
            ->getComponentByClassName(Timing::class, true);
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__START);

        $destinationUri = new Uri($destinationUrl);
        $ftpConnection = ftp_connect($destinationUri->getHost(), $destinationUri->getPort() ?? 21);

        if (empty($ftpConnection)) {
            // TODO: set error status for log.
            $this->reactOnError($webService, $analyzableContent);
            return;
        }

        // TODO: settings file.
        $userInfo = $destinationUri->getUserInfo();
        $parts = explode(':', $userInfo);

        if (count($parts) !== 2) {
            // No valid user info.
            // TODO: set error status for log.
            $this->reactOnError($webService, $analyzableContent, $ftpConnection);
            return;
        }

        $user = $parts[0];
        $password = $parts[1];

        $loginResult = ftp_login(
            $ftpConnection,
            $user,
            $password
        );

        if (empty($loginResult)) {
            // Login failed.
            // TODO: set error status for log.
            $this->reactOnError($webService, $analyzableContent, $ftpConnection);
            return;
        }

        // Mandatory (at least on local dev environment) to retrieve data from
        // server. Enables FTP passive mode.
        ftp_pasv($ftpConnection, true);

        // Open some file to write to.
        $fileHandle = tmpfile();

        // Try to download $server_file and save to $local_file.
        $status = ftp_nb_fget(
            $ftpConnection,
            $fileHandle,
            $destinationUri->getPath(),
            FTP_BINARY
        );

        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STARTED_RECEIVING);

        if ($status === FTP_FAILED) {
            // Could not download file.
            // TODO: set error status for log.
            $webService->executeTasksForEvent(
                WebServiceInterface::PHASE__DESTINATION_REACH_FAILURE,
                $analyzableContent
            );

            $this->reactOnError($webService, $analyzableContent, $ftpConnection, $fileHandle);
            return;
        }

        // Inform the content type.
        // TODO: log those metadata (entire stream_get_meta_data($fileHandle)).
        $path = stream_get_meta_data($fileHandle)['uri'];
        $mime = mime_content_type($path);
        header("Content-Type: $mime");

        // Start streaming back.
        $contentReadBytes = 0;

        /** @var Body $body */
        $body = $analyzableContent->getComponentByClassName(Body::class, true);
        $body->addComponentByClassName(DestinationResponseBodyAnalyzer::class);

        while ($status === FTP_MOREDATA) {
            // Still getting data from destination web service.
            // Get content without the already read content.
            $content = stream_get_contents($fileHandle, -1, $contentReadBytes);

            if ($content === false) {
                // Stop here, a failure occurred.
                break;
            }

            echo $content;

            // Log the content.
            $body->write($content);

            $contentReadBytes += strlen($content);

            $status = ftp_nb_continue($ftpConnection);
        }

        // Close FTP resource.
        ftp_close($ftpConnection);

        // Close the file resource.
        fclose($fileHandle);

        if ($status !== FTP_FINISHED) {
            // A failure occurred.
            // TODO: set error status for log.
            $this->reactOnError($webService, $analyzableContent);
            return;
        }

        // Data transferred.
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STOP);

        $webServiceStatus->setStatus(FtpWebServiceStatus::OK);

        $webService->executeTasksForEvent(WebServiceInterface::PHASE__EMITTED_RESPONSE, $analyzableContent);
    }

    /**
     * Call this when processor fails.
     *
     * @param WebServiceInterface $webService
     *   WebService object.
     * @param AnalyzableContent $analyzableContent
     *   The container for logging.
     * @param resource $ftpConnection
     *   FTP connection resource.
     * @param resource $fileHandle
     *   File handle. May be left empty if not alive.
     */
    private function reactOnError(
        WebServiceInterface $webService,
        AnalyzableContent $analyzableContent,
        $ftpConnection = null,
        $fileHandle = null
    ) {
        /** @var Timing $destinationResponseWaitComponent */
        $destinationResponseWaitComponent = $analyzableContent->getComponentByClassName(Timing::class, true);
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STOP);

        /** @var FtpWebServiceStatus $webServiceStatus */
        $webServiceStatus = $analyzableContent->addComponentByClassName(FtpWebServiceStatus::class);
        // TODO: FTP statuses are not enough specific about the issue.
        $webServiceStatus->setStatus(FtpWebServiceStatus::NOT_REACHABLE, true);

        $webService->executeTasksForEvent(WebServiceInterface::PHASE__EMITTED_RESPONSE, $analyzableContent);

        if (!empty($ftpConnection)) {
            ftp_close($ftpConnection);
        }

        if (!empty($fileHandle)) {
            fclose($fileHandle);
        }
        return;
    }
}

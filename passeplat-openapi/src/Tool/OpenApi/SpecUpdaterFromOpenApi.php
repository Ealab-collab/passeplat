<?php

namespace PassePlat\Openapi\Tool\OpenApi;

use Mthole\OpenApiMerge\FileHandling\File;
use Mthole\OpenApiMerge\FileHandling\SpecificationFile;
use Mthole\OpenApiMerge\Merge\PathMerger;
use Mthole\OpenApiMerge\Merge\ReferenceNormalizer;
use Mthole\OpenApiMerge\OpenApiMerge;
use Mthole\OpenApiMerge\Reader\FileReader;
use Mthole\OpenApiMerge\Writer\DefinitionWriter;
use PassePlat\Openapi\Exception\SpecMergeFailureException;
use PassePlat\Openapi\Exception\MissingParameterException;

/**
 * Implements the UpdateStrategy interface for updating an OpenAPI specification
 * by merging a base OpenAPI with an additional OpenAPI and saving the result.
 */
class SpecUpdaterFromOpenApi implements SpecUpdaterStrategy
{
    /**
     * Updates the OpenAPI specification from an additional OpenAPI.
     *
     * @param array $params
     *   Parameters and data for the update:
     *   -'additionalOpenAPI': The additional OpenAPI object to be merged.
     *   -'baseOpenAPI': The base OpenAPI object.
     *   -'extensionOutput': (Optional) The extension for the output file (yaml, yml, or json). Default is 'yaml'.
     *   -'resolveReference': (Optional) Whether to resolve references during the merge. Default is true.
     *
     * @throws MissingParameterException
     *   If required parameters are missing.
     * @throws SpecMergeFailureException
     *   If the merging process fails
     */
    public function update(array $params): void
    {
        try {
            /** @var OpenApiSpecHandler $baseOpenApi */
            $baseOpenApi = $params['baseOpenAPI'];
            $additionalOpenApi = $params['additionalOpenAPI'];

            if (empty($baseOpenApi) || empty($additionalOpenApi)) {
                throw new MissingParameterException('Base OpenAPI and additional OpenAPI must be provided.');
            }

            $extensionOutput = $params['extensionOutput'] ?? 'yaml';

            if (!in_array($extensionOutput, ['yaml', 'yml', 'json'])) {
                throw new SpecMergeFailureException(
                    $extensionOutput
                    . " output extension is not supported yet."
                    . " Only 'YAML', 'YML', and 'JSON' are supported."
                );
            }

            $resolveReference = $params['resolveReference'] ?? true;

            //TODO
            // Fix the issue with resolving references in the merge tool, or report it on GitHub.
            if ($resolveReference !== true) {
                throw new SpecMergeFailureException("Option 'resolve-references' is not supported yet.");
            }

            $openApiReader = new FileReader();
            $pathMerger = new PathMerger();
            $referenceResolver = new ReferenceNormalizer();
            $merger = new OpenApiMerge($openApiReader, $pathMerger, $referenceResolver);

            $baseFile = new File($baseOpenApi->getPath());

            $mergedResult = $merger->mergeFiles(
                $baseFile,
                [new File($additionalOpenApi->getPath())],
                $resolveReference
            );

            $definitionWriter = new DefinitionWriter();

            $specificationFile = new SpecificationFile(
                $baseFile,
                $mergedResult->getOpenApi()
            );

            file_put_contents(
                $baseFile->getAbsoluteFile(),
                $definitionWriter->write($specificationFile)
            );
        } catch (MissingParameterException|SpecMergeFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new SpecMergeFailureException($e->getMessage());
        }
    }
}

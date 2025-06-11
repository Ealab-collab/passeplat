<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\Tool\PropertiesComparer;

/**
 * Base class for JSON body conditions containing common methods.
 */
abstract class JsonBodyConditionBase_0 extends ConditionBase
{
    use ExecutionTraceProviderTrait;

    const BODY_MAX_LENGTH = 5000;
    const EXPECTED_VALUES_MAX_LENGTH = 1000;
    const JSONPATH_MAX_LENGTH = 250;

    protected function getPluginDescription(): array
    {
        // This abstract class is not meant to be used directly, so we don't set a public name and ID.
        // Don't forget to set the public name and ID in the child class.
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['version'] = '0';
        // Todo: réfléchir à ce système de "appliesTo" pour limiter la visibilité des conditions dans l'UI.
        $description['appliesTo'] = ['task'];

        // JSONPath string.
        $description['optionsSchema']['jsonpath'] = [
            'type' => 'string',
            // Dev note: we limit the JSON path length to limit denial of service attacks.
            // This limit can be increased if needed, but keep in mind the security implications.
            'max_chars' => static::JSONPATH_MAX_LENGTH,
            'default' => '',
        ];

        // The expected values which must be matched with the content extracted by the JSONPath.
        // It will be deserialized using the JSON decoder and compared with the extracted content.
        $description['optionsSchema']['expectedValues'] = [
            'type' => 'string',
            // Dev note: we limit the expected values length to limit denial of service attacks.
            // This limit can be increased if needed, but keep in mind the security implications.
            // Dev note: we also limit the length of the analyzed JSON content to limit denial of service attacks.
            'max_chars' => static::EXPECTED_VALUES_MAX_LENGTH,
            'default' => '',
        ];

        return $description;
    }

    /**
     * Gets the RequestInfo or ResponseInfo from which to get the header field.
     *
     * @param AnalyzableContent $analyzableContent
     *   The AnalyzableContent to get the stream info from.
     *
     * @return RequestInfo|ResponseInfo|null
     *   The RequestInfo or ResponseInfo from which to get the header field. May return null.
     *   TODO: use RequestInfo|ResponseInfo|null as return type when passing to PHP8.
     */
    abstract protected function getStreamInfoComponent(AnalyzableContent $analyzableContent);

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        if (empty($this->options['jsonpath'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No JSON path provided.');
            throw new ConditionException('No JSON path provided.');
        }

        if (empty($this->options['expectedValues'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No expected values provided.');
            throw new ConditionException('No expected values provided.');
        }

        // Decode using the non-associative array option to prevent empty object to array conversion.
        $decodedExpectedValues = json_decode($this->options['expectedValues'], false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('The expected values could not be parsed as JSON.');
            throw new ConditionException('The expected values could not be parsed as JSON.');
        }

        $streamInfo = $this->getStreamInfoComponent($analyzableContent);

        if (!$streamInfo) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream info found. This condition may be misplaced.');
            throw new ConditionException('No stream info found. This condition may be misplaced.');
        }

        /** @var Body $body */
        $body = $streamInfo->getComponentByClassName(Body::class);

        if (!$body->isBodyAnalyzable()) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                'The body is not analyzable. It may be too big or not ready for analysis yet.'
            );
            throw new ConditionException(
                'The body is not analyzable. It may be too big or not ready for analysis yet.'
            );
        }

        if ($body->getRealBodyLength() >= static::BODY_MAX_LENGTH) {
            // Todo: traduction.
            $bodyMaxLength = static::BODY_MAX_LENGTH;
            $this->addConditionExecutionTraceString(<<<INFO
The body is too big for evaluation. It must be smaller than $bodyMaxLength characters.
INFO);
            throw new ConditionException(<<<INFO
The body is too big for evaluation. It must be smaller than $bodyMaxLength characters.
INFO);
        }

        // Same here: decode using the non-associative array option to prevent empty object to array conversion.
        $decodedBody = json_decode($body->getBody(), false);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('The body could not be parsed as JSON.');
            throw new ConditionException('The body could not be parsed as JSON.');
        }

        try {
            $bodyExtract = (new JSONPath($decodedBody))->find($this->options['jsonpath'])->getData();
        } catch (JSONPathException $e) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('The body could not be parsed by JSONPath.');
            throw new ConditionException('The body could not be parsed by JSONPath.');
        }

        if (!PropertiesComparer::compareAny($bodyExtract, $decodedExpectedValues)) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                'The body does not match the expected values. The condition is not met.'
            );
            return false;
        }

        // Todo: traduction.
        $this->addConditionExecutionTraceString(
            'The body matches the expected values. The condition is met.'
        );
        return true;
    }
}

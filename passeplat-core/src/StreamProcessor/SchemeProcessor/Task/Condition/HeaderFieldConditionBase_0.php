<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderField;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;

/**
 * Base class for header field conditions containing common methods.
 */
abstract class HeaderFieldConditionBase_0 extends ConditionBase
{
    use ExecutionTraceProviderTrait;

    protected function getPluginDescription(): array
    {
        // This abstract class is not meant to be used directly, so we don't set a public name and ID.
        // Don't forget to set the public name and ID in the child class.
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['version'] = '0';
        // Todo: réfléchir à ce système de "appliesTo" pour limiter la visibilité des conditions dans l'UI.
        $description['appliesTo'] = ['task'];
        $description['optionsSchema']['headerFieldName'] = [
            'type' => 'string',
            // Dev note: there is no limit to the header field name and value length in the HTTP RFC.
            // The only common limit is the max size of the entire header, which is 8Ko.
            'max_chars' => 8192,
            'default' => '',
        ];
        $description['optionsSchema']['headerFieldValue'] = [
            'type' => 'string',
            'max_chars' => 8192,
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
        if (empty($this->options['headerFieldName'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('Invalid configuration: no header field name provided.');
            throw new ConditionException('Invalid configuration: no header field name provided.');
        }

        $streamInfo = $this->getStreamInfoComponent($analyzableContent);

        if (!$streamInfo) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream info found. This condition may be misplaced.');
            throw new ConditionException('No stream info found. This condition may be misplaced.');
        }

        /** @var Header $streamHeaders */
        $streamHeaders = $streamInfo->getComponentByClassName(Header::class);

        if (!$streamHeaders) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream headers found. This condition may be misplaced.');
            throw new ConditionException('No stream headers found. This condition may be misplaced.');
        }

        /** @var HeaderField $selectedHeaderField */
        $selectedHeaderField = null;
        $liveHeaderFieldValue = '';

        /** @var HeaderField $headerField */
        foreach ($streamHeaders->getComponentsByClassName(HeaderField::class) as $headerField) {
            if (strtolower($headerField->getName()) !== strtolower($this->options['headerFieldName'])) {
                continue;
            }

            // Todo: la présence du même champ d'en-tête sur plusieurs lignes n'est pas standard, sauf pour set-cookie.
            //   Pour le moment, on se base sur le fait qu'on prendra la première valeur trouvée. Mais à l'avenir,
            //   il faudra peut-être rendre cette règle configurable: on prend la première valeur, ou la dernière,
            //   ou on vérifie que toutes les valeurs sont identiques, ou on rejette lorsque plusieurs lignes sont
            //   présentes alors qu'elles ne devraient pas.
            $selectedHeaderField = $headerField;
            $liveHeaderFieldValue = $headerField->getValue();
            break;
        }

        // Trim the value to avoid false negatives.
        if ($selectedHeaderField instanceof HeaderField && method_exists($selectedHeaderField, 'getSeparator')) {
            // The header field supports multiple values.
            $separator = $selectedHeaderField->getSeparator();
            $trimmedLiveHeaderFieldValues = implode(
                $separator,
                array_map('trim', explode($separator, $liveHeaderFieldValue))
            );
            $trimmedHeaderFieldValuesToMatch = implode(
                $separator,
                array_map('trim', explode($separator, $this->options['headerFieldValue'] ?? ''))
            );
        } else {
            // The header field does not support multiple values.
            // Simply directly trim the values.
            $trimmedLiveHeaderFieldValues = trim($liveHeaderFieldValue);
            $trimmedHeaderFieldValuesToMatch = trim($this->options['headerFieldValue'] ?? '');
        }

        // We check if the values of the header match the ones we are looking for.
        // The order is also checked to match the RFC. This could be made configurable in the future if needed.
        if ($trimmedLiveHeaderFieldValues === $trimmedHeaderFieldValuesToMatch) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                "Value matched for the header field: {$this->options['headerFieldName']}"
            );
            return true;
        }

        // Todo: traduction.
        $this->addConditionExecutionTraceString(
            "Value didn't match for the header field: {$this->options['headerFieldName']}"
        );
        return false;
    }
}

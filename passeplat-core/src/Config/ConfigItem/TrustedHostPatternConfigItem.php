<?php

namespace PassePlat\Core\Config\ConfigItem;

/**
 * Contains trusted host pattern config.
 */
class TrustedHostPatternConfigItem extends ConfigItem
{
    private $trustedHostPatterns = [];
    private $trustedHostPatternsWithDestination = [];
    private $trustedHostPatternsWithUserId = [];

    public function getConfigId(): string
    {
        return 'trusted-host-patterns.passeplat-core';
    }

    /**
     * Trusted host patterns for host validation.
     *
     * @return string[]
     *   Array of trusted host patterns.
     */
    public function getTrustedHostPatterns(): array
    {
        return $this->trustedHostPatterns;
    }

    /**
     * Trusted host patterns with destination for host validation.
     *
     * @return string[]
     *   Array of trusted host patterns with destination.
     */
    public function getTrustedHostPatternsWithDestination(): array
    {
        return $this->trustedHostPatternsWithDestination;
    }

    /**
     * Trusted host patterns with user ID for host validation.
     *
     * @return string[]
     *   Array of trusted host patterns with user ID.
     */
    public function getTrustedHostPatternsWithUserId(): array
    {
        return $this->trustedHostPatternsWithUserId;
    }

    public function setValues(array $values): void
    {
        parent::setValues($values);

        $this->trustedHostPatterns = [];
        $this->trustedHostPatternsWithDestination = [];
        $this->trustedHostPatternsWithUserId = [];

        if (!empty($values['trustedHostPatterns'])) {
            $this->trustedHostPatterns = $values['trustedHostPatterns'];
            array_walk($this->trustedHostPatterns, function (&$arr) {
                // Make the patterns case insensitive.
                $arr = '{' . $arr . '}i';
            });
        }

        if (!empty($values['trustedHostPatternsWithDestination'])) {
            $this->trustedHostPatternsWithDestination = $values['trustedHostPatternsWithDestination'];
            array_walk($this->trustedHostPatternsWithDestination, function (&$arr) {
                // Make the patterns case insensitive.
                $arr = '{' . $arr . '}i';
            });
        }

        if (!empty($values['trustedHostPatternsWithUserId'])) {
            $this->trustedHostPatternsWithUserId = $values['trustedHostPatternsWithUserId'];
            array_walk($this->trustedHostPatternsWithUserId, function (&$arr) {
                // Make the patterns case insensitive.
                $arr = '{' . $arr . '}i';
            });
        }
    }
}

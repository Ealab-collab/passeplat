<?php

namespace PassePlat\Openapi\Tool\Har;

/**
 * This interface defines the strategies for building entries in the Har class.
 */
interface HarEntryBuilderStrategy
{
    /**
     * Build an entry using the specified strategy.
     *
     * @param array $parameters
     *   The parameters and data for the entry.
     *
     * @return array<string, mixed>
     *   The entry in HAR format to be added.
     */
    public function buildEntry(array $parameters): array;
}

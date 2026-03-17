<?php

declare(strict_types = 1);

namespace Propel\Tests\Attributes;

use Attribute;

/**
 * Attribute class for test data providers that test against files.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ComparesGeneratedFile
{
    /**
     * @param int $fileNamePosition
     * @param int $textPosition
     * @param string|null $textBuilder
     */
    public function __construct(
        public int $fileNamePosition = 0,
        public int $textPosition = 1,
        public string|null $textBuilder = null
    ) {
    }
}

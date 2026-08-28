<?php

namespace Propel\Tests\Generator\Model\Datatype;

use Propel\Generator\Model\Datatype\ColumnType;
use Propel\Tests\TestCase;
use RuntimeException;

class ColumnTypeTest extends TestCase
{
    public function testFromLiteral(): void
    {
        $type = ColumnType::fromLiteral('iNtEgEr');
        $this->assertSame(ColumnType::INTEGER, $type);
    }

    public function testFromLiteralException(): void
    {
        $this->expectException(RuntimeException::class);
        ColumnType::fromLiteral('asdf');
    }
}
